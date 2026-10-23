<?php

namespace App\Services;

use App\Exceptions\ApplicantFlowException;
use App\Models\Application;
use App\Models\AppSetting;
use App\Models\ConfigVersion;
use App\Models\EstimateDraft;
use App\Models\EstimateSubmission;
use App\Models\Interview;
use App\Models\PermissionEvent;
use App\Models\ScreeningResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;

class EstimateService
{
    public const MAX_ITEM_AMOUNT = 100_000_000;

    public const MAX_ITEM_HOURS = 9_999;

    public const MAX_DELIVERY_WEEKS = 156;

    /** 判定には使わず、管理者が比べるために答えてもらう追加の単価（税別・円） */
    public const EXTRA_RATES = [
        'extra_page' => '追加1ページの制作（デザイン・コーディング）',
        'extra_post' => '記事・コンテンツ1件の追加登録',
        'extra_revision' => '追加の修正（1回）',
        'extra_meeting' => '追加の打ち合わせ（1回）',
        'monthly_maintenance' => '公開後の月額保守（1か月）',
    ];

    /** 下書きとして保存し、確認・提出で再検証する入力欄 */
    public const FIELDS = ['amounts', 'hours', 'zero_reasons', 'scope_conditions', 'notes', 'delivery_weeks', 'extra_rates', 'alt_proposal', 'alt_total'];

    public const AGREEMENTS = [
        'include_all_scope' => '指定された見積範囲をすべて含む',
        'full_scope_capable' => 'すべての工程を本人またはチームで一括対応できる',
        'no_guarantee' => '見積の提出・面談は、契約・発注の確約ではないことを理解した',
    ];

    public function __construct(private MailQueue $mail, private InvitationService $invitations) {}

    /** 全角数字・カンマ・空白を取り除いた文字列にする。数値への変換はしない。 */
    public static function normalizeAmount(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            return is_array($value) ? '__invalid__' : '';
        }

        return preg_replace('/[\s,，円]/u', '', mb_convert_kana((string) $value, 'n'));
    }

    /**
     * 入力を正規化して検証する。$complete = true は確認・提出用（すべて必須）。
     *
     * @return array{data: array, errors: MessageBag, total: int}
     */
    public function validate(ConfigVersion $config, array $input, bool $complete): array
    {
        $errors = new MessageBag;
        $amounts = [];
        $hours = [];
        $zeroReasons = [];
        $total = 0;
        $rawAmounts = is_array($input['amounts'] ?? null) ? $input['amounts'] : [];
        $rawHours = is_array($input['hours'] ?? null) ? $input['hours'] : [];
        $rawReasons = is_array($input['zero_reasons'] ?? null) ? $input['zero_reasons'] : [];

        foreach ($config->estimate_items as $item) {
            $key = $item['key'];
            $label = $item['label'];
            $value = self::normalizeAmount($rawAmounts[$key] ?? '');
            $reason = is_string($rawReasons[$key] ?? null) ? trim($rawReasons[$key]) : '';

            if ($value === '') {
                $amounts[$key] = null;
                if ($complete) {
                    $errors->add("amounts.$key", "{$label}の金額を入力してください。0円の場合も0と入力してください。");
                }
            } elseif (! preg_match('/^\d{1,10}$/', $value)) {
                $amounts[$key] = null;
                $errors->add("amounts.$key", "{$label}の金額は、0以上の整数（円）で入力してください。小数やマイナスは使えません。");
            } elseif ((int) $value > self::MAX_ITEM_AMOUNT) {
                $amounts[$key] = null;
                $errors->add("amounts.$key", "{$label}の金額が大きすぎます。1億円以下で入力してください。");
            } else {
                $amounts[$key] = (int) $value;
                $total += (int) $value;
            }

            $hourValue = self::normalizeAmount($rawHours[$key] ?? '');
            if ($hourValue === '') {
                $hours[$key] = null;
                if ($complete) {
                    $errors->add("hours.$key", "{$label}の想定工数（時間）を入力してください。");
                }
            } elseif (! preg_match('/^\d{1,5}$/', $hourValue) || (int) $hourValue > self::MAX_ITEM_HOURS) {
                $hours[$key] = null;
                $errors->add("hours.$key", "{$label}の想定工数は、0以上の整数（時間）で、".number_format(self::MAX_ITEM_HOURS).'時間以下で入力してください。');
            } else {
                $hours[$key] = (int) $hourValue;
                if ($complete && $amounts[$key] !== null && $amounts[$key] > 0 && $hours[$key] < 1) {
                    $errors->add("hours.$key", "{$label}に金額があるため、想定工数は1時間以上で入力してください。");
                }
            }

            if (mb_strlen($reason) > 200) {
                $errors->add("zero_reasons.$key", "{$label}の0円の理由は200文字以内で入力してください。");
            }
            $zeroReasons[$key] = $reason !== '' ? $reason : null;
            if ($complete && $amounts[$key] === 0 && $reason === '') {
                $errors->add("zero_reasons.$key", "{$label}が0円の場合は、理由（例：他の工程に含む）を入力してください。");
            }
        }

        $scope = is_string($input['scope_conditions'] ?? null) ? trim($input['scope_conditions']) : '';
        $notes = is_string($input['notes'] ?? null) ? trim($input['notes']) : '';
        if (mb_strlen($scope) > 2000) {
            $errors->add('scope_conditions', '対応範囲・条件は2000文字以内で入力してください。');
        }
        if (mb_strlen($notes) > 2000) {
            $errors->add('notes', '見積の補足は2000文字以内で入力してください。');
        }
        if ($complete && $scope === '') {
            $errors->add('scope_conditions', '対応範囲・条件を入力してください。');
        }

        if ($complete && $notes === '') {
            $errors->add('notes', '備考に、合計金額になった理由や前提を入力してください。');
        }

        $deliveryRaw = self::normalizeAmount($input['delivery_weeks'] ?? '');
        $deliveryWeeks = null;
        if ($deliveryRaw === '') {
            if ($complete) {
                $errors->add('delivery_weeks', 'このモックを作る場合の納期（着手から公開まで）を、週で入力してください。');
            }
        } elseif (! preg_match('/^\d{1,3}$/', $deliveryRaw) || (int) $deliveryRaw < 1 || (int) $deliveryRaw > self::MAX_DELIVERY_WEEKS) {
            $errors->add('delivery_weeks', '納期は、1〜'.self::MAX_DELIVERY_WEEKS.'の整数（週）で入力してください。');
        } else {
            $deliveryWeeks = (int) $deliveryRaw;
        }

        $extraRates = [];
        $rawRates = is_array($input['extra_rates'] ?? null) ? $input['extra_rates'] : [];
        foreach (self::EXTRA_RATES as $key => $label) {
            $value = self::normalizeAmount($rawRates[$key] ?? '');
            if ($value === '') {
                $extraRates[$key] = null;
                if ($complete) {
                    $errors->add("extra_rates.$key", "{$label}の単価を入力してください。");
                }
            } elseif (! preg_match('/^\d{1,10}$/', $value) || (int) $value > self::MAX_ITEM_AMOUNT) {
                $extraRates[$key] = null;
                $errors->add("extra_rates.$key", "{$label}の単価は、0以上の整数（円）で、1億円以下で入力してください。");
            } else {
                $extraRates[$key] = (int) $value;
            }
        }

        $altProposal = is_string($input['alt_proposal'] ?? null) ? trim($input['alt_proposal']) : '';
        if (mb_strlen($altProposal) > 2000) {
            $errors->add('alt_proposal', '別パターンの提案は2000文字以内で入力してください。');
        }
        $altRaw = self::normalizeAmount($input['alt_total'] ?? '');
        $altTotal = null;
        if ($altRaw !== '') {
            if (! preg_match('/^\d{1,10}$/', $altRaw) || (int) $altRaw > self::MAX_ITEM_AMOUNT * 10) {
                $errors->add('alt_total', '別パターンの合計金額は、0以上の整数（円）で入力してください。');
            } else {
                $altTotal = (int) $altRaw;
            }
        }
        if ($altTotal !== null && $altProposal === '') {
            $errors->add('alt_proposal', '別パターンの合計金額を入力した場合は、どのような内容か（条件の違い）も入力してください。');
        }

        $agreements = [];
        $rawAgreements = is_array($input['agreements'] ?? null) ? $input['agreements'] : [];
        foreach (self::AGREEMENTS as $key => $label) {
            $agreements[$key] = ($rawAgreements[$key] ?? null) === '1';
            if ($complete && ! $agreements[$key]) {
                $errors->add("agreements.$key", "「{$label}」の確認にチェックしてください。");
            }
        }

        if ($complete && $errors->isEmpty() && $total < 1) {
            $errors->add('total', '合計金額が0円です。見積を入力してください。');
        }

        return [
            'data' => [
                'amounts' => $amounts,
                'hours' => $hours,
                'zero_reasons' => $zeroReasons,
                'scope_conditions' => $scope !== '' ? $scope : null,
                'notes' => $notes !== '' ? $notes : null,
                'delivery_weeks' => $deliveryWeeks,
                'extra_rates' => $extraRates,
                'alt_proposal' => $altProposal !== '' ? $altProposal : null,
                'alt_total' => $altTotal,
                'agreements' => $agreements,
            ],
            'errors' => $errors,
            'total' => $total,
        ];
    }

    public function saveDraft(Application $application, array $data): EstimateDraft
    {
        $draft = EstimateDraft::query()->updateOrCreate(['application_id' => $application->id], $data);
        if (in_array($application->status, ['received', 'invited'], true)) {
            $application->update(['status' => 'drafting']);
        }

        return $draft;
    }

    public function requireJudgeableConfig(Application $application): ConfigVersion
    {
        $config = $application->configVersion;
        if (! $config || ! $config->isJudgeable()) {
            app(AdminAlert::class)->raise('判定設定が不足しているため見積を受け付けられません', $application, 'config-incomplete:'.$application->id.':'.($config?->id ?? 0));
            throw new ApplicantFlowException('config_incomplete');
        }

        return $config;
    }

    /**
     * 正式提出。入力検証・再計算・判定・保存を1つのトランザクションで行う。
     * 金額はクライアントから受け取らず、サーバーに保存済みの下書きだけを使う。
     * 同じ冪等キーの再送には、保存済みの結果を返す。
     *
     * @return array{submission: EstimateSubmission, result: ScreeningResult, token: ?string}
     */
    public function submit(Application $application, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($application, $idempotencyKey) {
            $application = Application::query()->lockForUpdate()->findOrFail($application->id);

            $replay = EstimateSubmission::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($replay) {
                if ($replay->application_id !== $application->id) {
                    throw new ApplicantFlowException('invalid');
                }

                return ['submission' => $replay, 'result' => $replay->result, 'token' => null];
            }

            if (! $application->isP1()) {
                throw new ApplicantFlowException('invalid');
            }
            if ($application->remainingSubmissions() < 1) {
                throw new ApplicantFlowException('already_submitted');
            }

            $config = $this->requireJudgeableConfig($application);
            $draft = $application->draft;
            $checked = $this->validate($config, $draft ? $draft->only(self::FIELDS) + [
                'agreements' => array_map(fn ($v) => $v ? '1' : '0', $draft->agreements ?? []),
            ] : [], true);
            if ($checked['errors']->isNotEmpty()) {
                throw ValidationException::withMessages($checked['errors']->toArray());
            }

            $items = [];
            foreach ($config->estimate_items as $item) {
                $items[] = [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'amount' => $checked['data']['amounts'][$item['key']],
                    'hours' => $checked['data']['hours'][$item['key']],
                    'zero_reason' => $checked['data']['zero_reasons'][$item['key']],
                ];
            }
            $total = array_sum(array_column($items, 'amount'));

            $submission = EstimateSubmission::create([
                'application_id' => $application->id,
                'sequence' => $application->submissions()->count() + 1,
                'idempotency_key' => $idempotencyKey,
                'config_version_id' => $config->id,
                'items' => $items,
                'total' => $total,
                'scope_conditions' => $checked['data']['scope_conditions'],
                'notes' => $checked['data']['notes'],
                'delivery_weeks' => $checked['data']['delivery_weeks'],
                'extra_rates' => $checked['data']['extra_rates'],
                'alt_proposal' => $checked['data']['alt_proposal'],
                'alt_total' => $checked['data']['alt_total'],
                'agreements' => $checked['data']['agreements'],
                'submitted_at' => now(),
            ]);

            [$outcome, $reasons] = $this->judge($application, $config, $items, $total, $checked['data']['agreements']);

            $result = ScreeningResult::create([
                'submission_id' => $submission->id,
                'application_id' => $application->id,
                'outcome' => $outcome,
                'reason_codes' => $reasons,
                'criteria' => [
                    'config_version_no' => $config->version_no,
                    'total_cap' => $config->total_cap,
                    'item_caps_enabled' => $config->item_caps_enabled,
                    'item_caps' => $config->item_caps_enabled ? array_column($config->estimate_items, 'cap', 'key') : null,
                    'min_experience_months' => 36,
                    'is_test_criteria' => $config->is_test_criteria,
                ],
                'config_version_id' => $config->id,
                'decided_at' => now(),
            ]);

            $interview = Interview::query()->firstOrCreate(['application_id' => $application->id]);
            $token = null;
            if ($outcome === 'passed') {
                $interview->update(['access_granted' => true, 'booking_status' => $interview->booking_status === 'not_guided' ? 'available' : $interview->booking_status]);
                PermissionEvent::create(['application_id' => $application->id, 'action' => 'auto_grant', 'reason' => '見積判定で通過（提出 #'.$submission->sequence.'）']);
                // 通過メールに載せる本人用URLのため、招待を発行し直す（旧URLは失効）
                $token = $this->invitations->issue($application);
            } else {
                $interview->update(['access_granted' => false]);
            }

            $application->update(['status' => 'submitted']);

            $vars = ['name' => $application->name, 'number' => $application->number];
            if ($outcome === 'passed') {
                $vars['booking_page_url'] = $this->invitations->inviteUrl($token);
            }
            $resultMail = ['passed' => 'p1_result_passed', 'held' => 'p1_result_held', 'failed' => 'p1_result_failed'][$outcome];
            $this->mail->enqueue($resultMail, $application, [$application->email], $vars, 'submission:'.$submission->id.':applicant');
            $this->mail->enqueue('admin_submission', $application, AppSetting::notifyEmails(), [
                'number' => $application->number,
                'outcome' => ScreeningResult::OUTCOME_LABELS[$outcome],
                'admin_url' => route('admin.applications.show', $application),
            ], 'submission:'.$submission->id.':admin');

            return ['submission' => $submission, 'result' => $result, 'token' => $token];
        });
    }

    /** @return array{0: string, 1: list<string>} */
    public function judge(Application $application, ConfigVersion $config, array $items, int $total, array $agreements): array
    {
        $reasons = [];
        if (! $application->isP1()) {
            $reasons[] = 'not_p1';
        }
        foreach (self::AGREEMENTS as $key => $_) {
            if (empty($agreements[$key])) {
                $reasons[] = 'missing_agreement';
                break;
            }
        }
        if ($application->experienceMonths() < 36) {
            $reasons[] = 'experience_under_3y';
        }
        if (! $application->can_full_scope || empty($agreements['full_scope_capable'])) {
            $reasons[] = 'no_full_scope';
        }
        if (! $application->can_online_meeting) {
            $reasons[] = 'no_online_meeting';
        }
        if (empty($agreements['include_all_scope'])) {
            $reasons[] = 'scope_not_included';
        }
        if ($total > $config->total_cap) {
            $reasons[] = 'total_over_cap';
        }
        if ($config->item_caps_enabled) {
            $caps = array_column($config->estimate_items, 'cap', 'key');
            foreach ($items as $item) {
                if (isset($caps[$item['key']]) && $item['amount'] > (int) $caps[$item['key']]) {
                    $reasons[] = 'item_over_cap';
                    break;
                }
            }
        }

        $reasons = array_values(array_unique($reasons));
        if (! $reasons) {
            return ['passed', []];
        }
        // 金額だけが理由の場合は、その場で見送りにせず「保留」とする。担当者が内容を見て、改めて結果を連絡する。
        $onlyMoney = array_diff($reasons, ScreeningResult::MONEY_REASONS) === [];

        return [$onlyMoney ? 'held' : 'failed', $reasons];
    }
}
