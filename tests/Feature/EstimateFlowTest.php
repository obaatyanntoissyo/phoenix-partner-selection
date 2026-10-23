<?php

namespace Tests\Feature;

use App\Models\EmailJob;
use App\Models\EstimateSubmission;
use App\Models\ScreeningResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\PartnerHelpers;
use Tests\TestCase;

class EstimateFlowTest extends TestCase
{
    use PartnerHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useTestCriteria();
        $this->configureSettings();
    }

    public function test_full_flow_passes_and_shows_booking_link(): void
    {
        $this->applyP1()->assertRedirect('/my/conditions');
        $this->get('/my/conditions')->assertOk()
            ->assertSeeText('見積もり用の制作条件（別タブで開く）')
            ->assertSee('未設定（確定後にお知らせします）');

        $this->get('/my/estimate')->assertOk();
        $this->submitEstimate(self::TEST_CAP - 1)->assertRedirect('/my/result');

        $this->get('/my/result')->assertOk()
            ->assertSee('今回の面談案内条件を満たしています')
            ->assertSee('面談日程の登録へ進む');
        $this->assertSame('passed', ScreeningResult::query()->first()->outcome);
        $this->assertTrue($this->latestApplication()->interview->access_granted);
        $passMail = EmailJob::query()->where('type', 'p1_result_passed')->firstOrFail();
        $this->assertStringContainsString('/invite/', $passMail->body);
        $this->assertTrue(EmailJob::query()->where('type', 'admin_submission')->exists());
    }

    /** 上限額は画面・HTMLに含めない */
    public function test_cap_is_never_rendered_to_applicant(): void
    {
        $this->applyP1();
        foreach (['/my/conditions', '/my/estimate'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString((string) self::TEST_CAP, $html);
            $this->assertStringNotContainsString(number_format(self::TEST_CAP), $html);
        }
        $this->post('/my/estimate', $this->estimateInput(500_000) + ['action' => 'confirm']);
        $html = $this->get('/my/confirm')->getContent();
        $this->assertStringNotContainsString(number_format(self::TEST_CAP), $html);
        $this->assertStringNotContainsString('上限', $html);
    }

    /** T03 必須不足・不正金額 → 提出不可、値を保持してエラー表示 */
    public function test_t03_missing_fields_keep_values(): void
    {
        $this->applyP1();
        $input = $this->estimateInput(500_000, ['amounts' => ['design' => ''], 'scope_conditions' => '', 'agreements' => ['no_guarantee' => '0']]);
        $this->post('/my/estimate', $input + ['action' => 'confirm'])->assertRedirect('/my/estimate');

        $this->get('/my/estimate')
            ->assertSee('デザインの金額を入力してください。')
            ->assertSee('対応範囲・条件を入力してください。')
            ->assertSee('契約・発注の確約ではないことを理解した」の確認にチェックしてください。', false)
            ->assertSee('value="'.$input['amounts']['coding'].'"', false);
        $this->get('/my/confirm')->assertRedirect('/my/estimate');
        $this->assertSame(0, EstimateSubmission::query()->count());
    }

    /** T04 小数・負数・過大値・数値でない入力 → サーバーで拒否 */
    public function test_t04_invalid_numbers_are_rejected(): void
    {
        $this->applyP1();
        foreach (['1.5', '-100', '100000001', 'abc', '1e5', '０x'] as $bad) {
            $this->post('/my/estimate', $this->estimateInput(500_000, ['amounts' => ['coding' => $bad]]) + ['action' => 'confirm'])
                ->assertRedirect('/my/estimate')
                ->assertSessionHasErrors('amounts.coding');
        }
        // 配列などの想定外の型でも壊れない
        $this->post('/my/estimate', ['amounts' => ['coding' => ['x' => 1]], 'action' => 'confirm'])->assertRedirect('/my/estimate');
        $this->assertSame(0, EstimateSubmission::query()->count());
        // 全角数字・カンマは正規化して受け付ける
        $this->post('/my/estimate', $this->estimateInput(500_000, ['amounts' => ['coding' => '５０,０００']]) + ['action' => 'save'])
            ->assertSessionHasNoErrors();
        $this->assertSame(50000, $this->latestApplication()->draft->amounts['coding']);
    }

    /** T05 総額が上限と同額 → 通過 */
    public function test_t05_total_equal_to_cap_passes(): void
    {
        $this->applyP1();
        $this->submitEstimate(self::TEST_CAP)->assertRedirect('/my/result');
        $result = ScreeningResult::query()->firstOrFail();
        $this->assertSame('passed', $result->outcome);
        $this->assertSame(self::TEST_CAP, EstimateSubmission::query()->first()->total);
    }

    /** T06 総額が上限＋1円 → 保留（見送りとは表示しない）、予約権限なし、結果は改めてメールで連絡 */
    public function test_t06_total_over_cap_by_one_yen_is_held_not_rejected(): void
    {
        $this->applyP1();
        $this->submitEstimate(self::TEST_CAP + 1)->assertRedirect('/my/result');

        $result = ScreeningResult::query()->firstOrFail();
        $this->assertSame('held', $result->outcome);
        $this->assertSame(['total_over_cap'], $result->reason_codes);
        $this->assertFalse($this->latestApplication()->interview->access_granted);
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_result_held')->count());
        $this->assertSame(0, EmailJob::query()->where('type', 'p1_result_failed')->count());

        $page = $this->get('/my/result')->assertSee('選考結果は、改めてメールでお送りします');
        $html = $page->getContent();
        $this->assertStringNotContainsString('見送り', $html);
        $this->assertStringNotContainsString('合致しなかった', $html);
        $this->assertStringNotContainsString('面談日程の登録へ進む', $html);
        $this->assertStringNotContainsString('上限', $html);
        $this->assertStringNotContainsString('booking.example.com', $html);
        $this->get('/my/booking')->assertForbidden();
    }

    /** 金額オーバーでも、経験3年未満・一括対応不可など金額以外の理由があれば不通過 */
    public function test_over_cap_with_other_reason_is_failed(): void
    {
        $this->applyP1(['experience_years' => '2', 'experience_months' => '0']);
        $this->submitEstimate(self::TEST_CAP + 1);

        $result = ScreeningResult::query()->firstOrFail();
        $this->assertSame('failed', $result->outcome);
        $this->assertEqualsCanonicalizing(['experience_under_3y', 'total_over_cap'], $result->reason_codes);
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_result_failed')->count());
        $this->get('/my/result')->assertSee('今回の募集条件とは合致しなかった');
    }

    /** 保留の応募を、管理者が通過として連絡する → 面談予約に進める */
    public function test_admin_can_pass_a_held_application_and_applicant_can_book(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $this->submitEstimate(self::TEST_CAP + 1);

        $this->actingAs($this->admin())
            ->post("/admin/applications/{$application->id}/send-result", ['result' => 'passed', 'reason' => '金額が僅差のため'])
            ->assertRedirect();

        $result = ScreeningResult::query()->sole();
        $this->assertSame('passed', $result->outcome);
        $this->assertContains('admin_decided', $result->reason_codes);
        $this->assertTrue($application->refresh()->interview->access_granted);
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_result_passed')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'send_result', 'reason' => '金額が僅差のため']);

        // 同じ操作は二重に送れない（結果は保留ではなくなる）
        $this->post("/admin/applications/{$application->id}/send-result", ['result' => 'passed', 'reason' => '再操作'])->assertNotFound();
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_result_passed')->count());
    }

    /** 保留の応募を、管理者が不通過として連絡する */
    public function test_admin_can_reject_a_held_application(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $this->submitEstimate(self::TEST_CAP + 1);

        $this->actingAs($this->admin())
            ->post("/admin/applications/{$application->id}/send-result", ['result' => 'failed', 'reason' => '予算に合わない'])
            ->assertRedirect();

        $this->assertSame('failed', ScreeningResult::query()->sole()->outcome);
        $this->assertFalse($application->refresh()->interview->access_granted);
        $this->assertSame('declined', $application->interview->stage);
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_result_failed')->count());
    }

    /** 管理画面：保留の応募はホームで知らせ、一覧で見積合計の低い順に探せ、詳細で結果を決められる */
    public function test_admin_screens_surface_held_applications(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $this->submitEstimate(self::TEST_CAP + 10_000);
        $this->flushSession();
        $this->actingAs($this->admin());

        $this->get('/admin')->assertOk()->assertSee('結果の連絡待ちが 1件');
        $this->get('/admin/applications?outcome=held&sort=total_asc')->assertOk()
            ->assertSee($application->number)->assertSee('10,000円 超過');
        $this->get("/admin/applications/{$application->id}")->assertOk()
            ->assertSee('結果を決めて、応募者に連絡してください')->assertSee('通過として連絡する')->assertSee('不通過として連絡する');
    }

    /** 保留でない応募には、結果の決定操作ができない */
    public function test_send_result_requires_held_application(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $this->submitEstimate(self::TEST_CAP);

        $this->actingAs($this->admin())
            ->post("/admin/applications/{$application->id}/send-result", ['result' => 'failed', 'reason' => 'x'])
            ->assertNotFound();
        $this->assertSame('passed', ScreeningResult::query()->sole()->outcome);
    }

    public function test_item_caps_are_applied_only_when_enabled(): void
    {
        $this->useTestCriteria(self::TEST_CAP, true, array_fill_keys(['direction', 'planning', 'design', 'coding', 'cms', 'form', 'content', 'testing', 'deploy', 'manual'], 50_000));
        $this->applyP1();
        $this->submitEstimate(600_000);
        $this->assertContains('item_over_cap', ScreeningResult::query()->first()->reason_codes);
    }

    /** T07 3年未満、または一括対応不可 → 不通過。理由は社内だけ */
    public function test_t07_experience_or_scope_failure_reason_is_internal_only(): void
    {
        $this->applyP1(['experience_years' => '2', 'experience_months' => '11']);
        $this->submitEstimate(100_000);
        $result = ScreeningResult::query()->firstOrFail();
        $this->assertSame('failed', $result->outcome);
        $this->assertSame(['experience_under_3y'], $result->reason_codes);
        $html = $this->get('/my/result')->getContent();
        $this->assertStringNotContainsString('3年', $html);
        $this->assertStringNotContainsString('経験', $html);

        $this->flushSession();
        $this->applyP1(['email' => 'other@example.com', 'can_full_scope' => '0', 'can_online_meeting' => '0']);
        $this->submitEstimate(100_000);
        $this->assertEqualsCanonicalizing(['no_full_scope', 'no_online_meeting'], ScreeningResult::query()->latest('id')->first()->reason_codes);

        $this->flushSession();
        $this->applyP1(['email' => 'three@example.com', 'experience_years' => '3', 'experience_months' => '0']);
        $this->submitEstimate(100_000);
        $this->assertSame('passed', ScreeningResult::query()->latest('id')->first()->outcome);
    }

    /** T08 0円理由なし・合計0円 → 提出不可 */
    public function test_t08_zero_without_reason_or_zero_total_is_rejected(): void
    {
        $this->applyP1();
        $this->post('/my/estimate', $this->estimateInput(500_000, ['amounts' => ['manual' => '0']]) + ['action' => 'confirm'])
            ->assertSessionHasErrors('zero_reasons.manual');

        $zeros = array_map(fn () => '0', $this->amounts(10));
        $reasons = array_map(fn () => '他工程に含む', $zeros);
        $this->post('/my/estimate', $this->estimateInput(0, ['amounts' => $zeros, 'zero_reasons' => $reasons]) + ['action' => 'confirm'])
            ->assertSessionHasErrors('total');
        $this->assertSame(0, EstimateSubmission::query()->count());

        // 理由があれば0円の工程は受け付ける
        $this->post('/my/estimate', $this->estimateInput(500_000, ['amounts' => ['manual' => '0'], 'zero_reasons' => ['manual' => 'ディレクションに含む']]) + ['action' => 'confirm'])
            ->assertRedirect('/my/confirm');
    }

    /** 想定工数は必須。金額がある工程は1時間以上。小数・マイナス・過大値は拒否し、提出内容に残る */
    public function test_hours_are_required_and_validated(): void
    {
        $this->applyP1();
        $missing = $this->estimateInput(500_000, ['hours' => ['design' => '']]);
        $this->post('/my/estimate', $missing + ['action' => 'confirm'])->assertSessionHasErrors('hours.design');

        foreach (['1.5', '-3', 'abc', '10000'] as $bad) {
            $this->post('/my/estimate', $this->estimateInput(500_000, ['hours' => ['design' => $bad]]) + ['action' => 'confirm'])
                ->assertSessionHasErrors('hours.design');
        }

        // 金額があるのに0時間は不可。0円の工程なら0時間でよい
        $this->post('/my/estimate', $this->estimateInput(500_000, ['hours' => ['design' => '0']]) + ['action' => 'confirm'])
            ->assertSessionHasErrors('hours.design');
        $this->post('/my/estimate', $this->estimateInput(500_000, ['amounts' => ['manual' => '0'], 'zero_reasons' => ['manual' => '設計に含む'], 'hours' => ['manual' => '0']]) + ['action' => 'confirm'])
            ->assertRedirect('/my/confirm');
        $this->assertSame(0, EstimateSubmission::query()->count());

        // 全角数字も受け付け、提出した内容に工数が残る
        $key = $this->goToConfirm($this->estimateInput(500_000, ['hours' => ['design' => '４０']]));
        $this->post('/my/submit', ['submit_key' => $key])->assertRedirect();
        $design = collect(EstimateSubmission::query()->firstOrFail()->items)->firstWhere('key', 'design');
        $this->assertSame(40, $design['hours']);
    }

    /** T09 クライアントの合計・passed・応募IDを改変 → サーバーで再計算・認可 */
    public function test_t09_client_side_tampering_is_ignored(): void
    {
        $this->applyP1();
        $victim = $this->latestApplication();
        $this->flushSession();
        $this->applyP1(['email' => 'attacker@example.com']);
        $attacker = $this->latestApplication();

        $key = $this->goToConfirm($this->estimateInput(self::TEST_CAP + 500_000));
        $this->post('/my/submit', [
            'submit_key' => $key,
            'total' => '1',
            'passed' => '1',
            'outcome' => 'passed',
            'application_id' => $victim->id,
            'category' => 'p2',
            'amounts' => $this->amounts(10),
        ])->assertRedirect('/my/result');

        $submission = EstimateSubmission::query()->firstOrFail();
        $this->assertSame($attacker->id, $submission->application_id);
        $this->assertSame(self::TEST_CAP + 500_000, $submission->total);
        $this->assertSame('held', $submission->result->outcome);
        $this->assertSame(0, $victim->submissions()->count());
    }

    /** T12 提出ボタン連打・通信再試行 → 提出・判定・通知が重複しない */
    public function test_t12_double_submit_is_idempotent(): void
    {
        $this->applyP1();
        $key = $this->goToConfirm($this->estimateInput(500_000));

        $this->post('/my/submit', ['submit_key' => $key])->assertRedirect('/my/result');
        $this->post('/my/submit', ['submit_key' => $key])->assertRedirect('/my/result');
        $this->post('/my/submit', ['submit_key' => str_repeat('a', 40)])->assertRedirect('/my/result');

        $this->assertSame(1, EstimateSubmission::query()->count());
        $this->assertSame(1, ScreeningResult::query()->count());
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_result_passed')->count());
        $this->assertSame(1, EmailJob::query()->where('type', 'admin_submission')->count());
    }

    /** T13 不通過後の戻る操作・再提出 → 基準探りを許可しない */
    public function test_t13_cannot_resubmit_after_failure(): void
    {
        $this->applyP1();
        $this->submitEstimate(self::TEST_CAP + 1);

        $this->get('/my/estimate')->assertRedirect('/my/result');
        $this->get('/my/confirm')->assertRedirect('/my/result');
        $this->post('/my/estimate', $this->estimateInput(100) + ['action' => 'confirm'])->assertRedirect('/my/result');
        $this->post('/my/submit', ['submit_key' => str_repeat('b', 40)])->assertRedirect('/my/result');

        $this->assertSame(1, EstimateSubmission::query()->count());
        $this->assertSame('held', ScreeningResult::query()->sole()->outcome);
    }

    /** T14 管理者が再提出許可 → 新提出を保存、旧履歴は残る */
    public function test_t14_admin_allows_resubmission_and_history_is_kept(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $this->submitEstimate(self::TEST_CAP + 1);
        $session = session('applicant');

        $this->actingAs($this->admin())
            ->post("/admin/applications/{$application->id}/allow-resubmission", ['reason' => '金額の入力ミスの申告'])
            ->assertRedirect();
        auth()->logout();

        $this->withSession(['applicant' => $session]);
        $this->get('/my/result')->assertSee('見積を修正して再提出する');
        $this->submitEstimate(self::TEST_CAP)->assertRedirect('/my/result');

        $results = ScreeningResult::query()->orderBy('id')->pluck('outcome')->all();
        $this->assertSame(['held', 'passed'], $results);
        $this->assertSame([1, 2], EstimateSubmission::query()->orderBy('id')->pluck('sequence')->all());
        $this->assertSame(self::TEST_CAP + 1, EstimateSubmission::query()->orderBy('id')->first()->total);
        $this->assertDatabaseHas('audit_logs', ['action' => 'allow_resubmission', 'reason' => '金額の入力ミスの申告']);
    }

    /** T16 DB保存失敗 → 提出完了・通過・予約可能と表示しない */
    public function test_t16_save_failure_does_not_show_success(): void
    {
        $this->applyP1();
        $key = $this->goToConfirm($this->estimateInput(500_000));

        // 判定結果の保存時にDBエラーが起きる状態を作る
        ScreeningResult::creating(function () {
            throw new \PDOException('simulated database failure');
        });
        $response = $this->post('/my/submit', ['submit_key' => $key]);
        ScreeningResult::flushEventListeners();

        $response->assertStatus(503)->assertSee('保存できませんでした')->assertSee('提出は完了していません');
        $this->assertStringNotContainsString('満たしています', $response->getContent());
        $this->assertStringNotContainsString('面談日程', $response->getContent());
        $this->assertSame(0, EstimateSubmission::query()->count());
        $this->assertFalse((bool) $this->latestApplication()->interview->access_granted);
    }

    /** T19 判定上限未設定 → 設定不備として停止。不採用扱いにしない */
    public function test_t19_missing_cap_stops_without_rejection(): void
    {
        $this->useTestCriteria(null);
        $this->applyP1();

        $this->get('/my/conditions')->assertOk();
        $response = $this->get('/my/estimate')->assertStatus(503)->assertSee('現在手続きを進められません');
        $this->assertStringNotContainsString('合致しなかった', $response->getContent());
        $this->assertSame(0, ScreeningResult::query()->count());
        $this->assertTrue(EmailJob::query()->where('type', 'admin_alert')->exists());

        // 同じ事象の通知は1回だけ
        $this->get('/my/estimate');
        $this->assertSame(1, EmailJob::query()->where('type', 'admin_alert')->count());
    }

    public function test_draft_is_saved_in_database_and_restored(): void
    {
        $this->applyP1();
        $session = session('applicant');
        $this->post('/my/estimate', ['amounts' => ['design' => '300000'], 'notes' => '途中です', 'action' => 'save'])
            ->assertRedirect('/my/estimate')->assertSessionHasNoErrors();

        $this->flushSession();
        $this->withSession(['applicant' => $session]);
        $this->get('/my/estimate')->assertSee('value="300000"', false)->assertSee('途中です');
        $this->assertSame('drafting', $this->latestApplication()->status);
    }
}
