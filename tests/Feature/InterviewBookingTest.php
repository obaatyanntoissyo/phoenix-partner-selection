<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\EmailJob;
use App\Models\Interview;
use App\Models\InterviewBooking;
use App\Models\InterviewSlot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\PartnerHelpers;
use Tests\TestCase;

/** 面談予約（このシステムで空き枠を選ぶ）と Google カレンダー連携 */
class InterviewBookingTest extends TestCase
{
    use PartnerHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useTestCriteria();
        $this->configureSettings();
        AppSetting::put('booking_mode', 'slots');
        AppSetting::put('booking_lead_hours', '24');
        Http::preventStrayRequests();
    }

    private function slot(int $daysAhead = 3, int $hour = 10): InterviewSlot
    {
        $start = today()->addDays($daysAhead)->setTime($hour, 0);

        return InterviewSlot::create(['starts_at' => $start, 'ends_at' => $start->copy()->addHour()]);
    }

    private function passedApplicant(string $email = 'taro@example.com'): void
    {
        $this->flushSession();
        $this->applyP1(['email' => $email]);
        $this->submitEstimate(self::TEST_CAP);
    }

    private function connectGoogle(): void
    {
        config(['partner.google.client_id' => 'test-client', 'partner.google.client_secret' => 'test-secret']);
        AppSetting::put('google_refresh_token', Crypt::encryptString('refresh-token-value'));
        AppSetting::put('google_account', 'recruit@example.com');
    }

    /** 予定作成 API が返す HTTP ステータス（テスト途中で「復旧」させるため、偽の応答から都度読む） */
    private int $insertStatus = 200;

    private function fakeGoogle(array $busy = [], int $insertStatus = 200): void
    {
        $this->insertStatus = $insertStatus;
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token', 'expires_in' => 3600]),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => $busy]]]),
            'www.googleapis.com/calendar/v3/calendars/*' => function (HttpRequest $request) {
                if ($request->method() === 'POST') {
                    return Http::response($this->insertStatus === 200 ? ['id' => 'x', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij'] : ['error' => ['code' => $this->insertStatus]], $this->insertStatus);
                }
                if ($request->method() === 'GET') {
                    return Http::response(['id' => 'x', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij']);
                }

                return Http::response(null, 204);
            },
        ]);
    }

    public function test_passed_applicant_books_a_slot_without_google(): void
    {
        $slot = $this->slot();
        $this->passedApplicant();
        $application = $this->latestApplication();

        $this->get('/my/booking')->assertOk()->assertSee('この日時で予約する')->assertSee($slot->starts_at->format('H:i'));
        $this->post('/my/booking/reserve', ['slot_id' => $slot->id])->assertRedirect('/my/booking');

        $booking = InterviewBooking::query()->sole();
        $this->assertSame($application->id, $booking->application_id);
        $this->assertSame('skipped', $booking->calendar_state);
        $interview = Interview::query()->where('application_id', $application->id)->sole();
        $this->assertSame('confirmed', $interview->booking_status);
        $this->assertSame('booked', $interview->stage);
        $this->assertTrue($interview->interview_at->equalTo($slot->starts_at));
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_booking_confirmed')->where('status', 'pending')->count());
        $this->assertSame(1, EmailJob::query()->where('type', 'admin_booking')->count());
        $this->assertStringContainsString($slot->label(), EmailJob::query()->where('type', 'p1_booking_confirmed')->sole()->body);

        // 予約後は日時を表示し、もう一度は予約できない
        $other = $this->slot(4);
        $this->get('/my/booking')->assertOk()->assertSee($slot->label())->assertDontSee('この日時で予約する');
        $this->post('/my/booking/reserve', ['slot_id' => $other->id])->assertRedirect('/my/booking')->assertSessionHasErrors('slot_id');
        $this->assertSame(1, InterviewBooking::query()->count());
    }

    public function test_only_passed_applicants_can_see_or_book_slots(): void
    {
        $slot = $this->slot();

        $this->post('/my/booking/reserve', ['slot_id' => $slot->id])->assertForbidden();
        $this->applyP1();
        $this->get('/my/booking')->assertForbidden();
        $this->post('/my/booking/reserve', ['slot_id' => $slot->id])->assertForbidden();
        $this->submitEstimate(self::TEST_CAP + 1);
        $this->get('/my/booking')->assertForbidden();
        $this->post('/my/booking/reserve', ['slot_id' => $slot->id])->assertForbidden();

        $this->assertSame(0, InterviewBooking::query()->count());
    }

    public function test_a_slot_cannot_be_booked_twice(): void
    {
        $slot = $this->slot();
        $this->passedApplicant('first@example.com');
        $this->post('/my/booking/reserve', ['slot_id' => $slot->id])->assertSessionHasNoErrors();

        $this->passedApplicant('second@example.com');
        $this->get('/my/booking')->assertOk()->assertDontSee($slot->starts_at->format('H:i').'〜');
        $this->post('/my/booking/reserve', ['slot_id' => $slot->id])->assertSessionHasErrors('slot_id');
        $this->assertSame(1, InterviewBooking::query()->count());

        // 同時実行で画面の確認をすり抜けても、DB の一意制約で止まる
        $this->expectException(QueryException::class);
        InterviewBooking::create([
            'slot_id' => $slot->id, 'application_id' => $this->latestApplication()->id, 'status' => 'active',
            'active_slot_id' => $slot->id, 'active_application_id' => $this->latestApplication()->id, 'calendar_event_id' => 'ppsduplicate',
        ]);
    }

    public function test_slots_inside_the_lead_time_are_hidden_and_rejected(): void
    {
        $soon = InterviewSlot::create(['starts_at' => now()->addHours(5)->startOfHour(), 'ends_at' => now()->addHours(6)->startOfHour()]);
        $later = $this->slot(3);
        $this->passedApplicant();

        $this->get('/my/booking')->assertOk()->assertSee('value="'.$later->id.'"', false)
            ->assertDontSee('value="'.$soon->id.'"', false);
        $this->post('/my/booking/reserve', ['slot_id' => $soon->id])->assertSessionHasErrors('slot_id');
        $this->assertSame(0, InterviewBooking::query()->count());
    }

    public function test_booking_creates_google_calendar_event_with_meet_and_attendee(): void
    {
        $this->connectGoogle();
        $this->fakeGoogle();
        $slot = $this->slot();
        $this->passedApplicant();

        $this->post('/my/booking/reserve', ['slot_id' => $slot->id])->assertRedirect('/my/booking');

        $booking = InterviewBooking::query()->sole();
        $this->assertSame('synced', $booking->calendar_state);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $booking->meet_url);
        $this->assertMatchesRegularExpression('/^[0-9a-v]{5,1024}$/', $booking->calendar_event_id);
        Http::assertSent(function (HttpRequest $request) use ($booking) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/calendars/primary/events?')
                && str_contains($request->url(), 'sendUpdates=all')
                && str_contains($request->url(), 'conferenceDataVersion=1')
                && $request['id'] === $booking->calendar_event_id
                && $request['attendees'][0]['email'] === 'taro@example.com'
                && $request['conferenceData']['createRequest']['conferenceSolutionKey']['type'] === 'hangoutsMeet'
                && $request->hasHeader('Authorization', 'Bearer access-token');
        });
        $this->get('/my/booking')->assertSee('https://meet.google.com/abc-defg-hij');
    }

    public function test_slots_overlapping_busy_calendar_time_are_hidden(): void
    {
        $this->connectGoogle();
        $busySlot = $this->slot(3, 10);
        $freeSlot = $this->slot(3, 13);
        $this->fakeGoogle([[
            'start' => $busySlot->starts_at->copy()->addMinutes(30)->utc()->toRfc3339String(),
            'end' => $busySlot->ends_at->copy()->addMinutes(30)->utc()->toRfc3339String(),
        ]]);
        $this->passedApplicant();

        $this->get('/my/booking')->assertOk()
            ->assertSee('value="'.$freeSlot->id.'"', false)
            ->assertDontSee('value="'.$busySlot->id.'"', false);
        $this->post('/my/booking/reserve', ['slot_id' => $busySlot->id])->assertSessionHasErrors('slot_id');
    }

    public function test_google_failure_keeps_the_booking_and_retries_until_dead(): void
    {
        $this->connectGoogle();
        $this->fakeGoogle([], 500);
        $slot = $this->slot();
        $this->passedApplicant();

        $this->post('/my/booking/reserve', ['slot_id' => $slot->id])->assertRedirect('/my/booking')->assertSessionHasNoErrors();
        $booking = InterviewBooking::query()->sole();
        $this->assertTrue($booking->isActive());
        $this->assertSame('pending', $booking->calendar_state);
        $this->assertSame(1, $booking->calendar_attempts);
        $this->assertStringContainsString('HTTP 500', $booking->calendar_error);

        for ($i = 0; $i < 5; $i++) {
            $this->travel(1)->hours();
            $this->artisan('partner:sync-calendar')->assertSuccessful();
        }
        $booking->refresh();
        $this->assertSame('dead', $booking->calendar_state);
        $this->assertSame(5, $booking->calendar_attempts);
        $this->assertTrue(AuditLog::query()->where('action', 'alert')->where('reason', 'like', 'Googleカレンダーへの登録失敗%')->exists());
        $this->assertSame(1, EmailJob::query()->where('type', 'admin_alert')->count());

        // 復旧後、管理者が再試行すると登録される
        $this->insertStatus = 200;
        $this->actingAs($this->admin())->post('/admin/bookings/'.$booking->id.'/retry')->assertSessionHasNoErrors();
        $this->assertSame('synced', $booking->refresh()->calendar_state);
    }

    public function test_retry_after_event_already_created_does_not_duplicate(): void
    {
        $this->connectGoogle();
        $this->fakeGoogle([], 409);
        $slot = $this->slot();
        $this->passedApplicant();

        $this->post('/my/booking/reserve', ['slot_id' => $slot->id]);
        $booking = InterviewBooking::query()->sole();
        $this->assertSame('synced', $booking->calendar_state);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $booking->meet_url);
    }

    public function test_admin_cancel_frees_the_slot_deletes_the_event_and_notifies(): void
    {
        $this->connectGoogle();
        $this->fakeGoogle();
        $slot = $this->slot();
        $this->passedApplicant();
        $this->post('/my/booking/reserve', ['slot_id' => $slot->id]);
        $booking = InterviewBooking::query()->sole();

        $this->actingAs($this->admin())
            ->post('/admin/bookings/'.$booking->id.'/cancel', ['reason' => '担当者の急用', 'notify' => '1'])
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertNull($booking->active_slot_id);
        $this->assertSame('deleted', $booking->calendar_state);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/events/'.$booking->calendar_event_id));
        $interview = Interview::query()->where('application_id', $booking->application_id)->sole();
        $this->assertSame('available', $interview->booking_status);
        $this->assertNull($interview->interview_at);
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_booking_cancelled')->count());

        // 枠は空きに戻り、同じ応募者が選び直せる
        $this->post('/my/booking/reserve', ['slot_id' => $slot->id])->assertSessionHasNoErrors();
        $this->assertSame(1, InterviewBooking::query()->where('status', 'active')->count());
    }

    public function test_admin_creates_slots_by_period_weekday_and_time(): void
    {
        $monday = today()->next('Monday');
        $this->actingAs($this->admin())->post('/admin/slots', [
            'date_from' => $monday->format('Y-m-d'),
            'date_to' => $monday->copy()->addDays(6)->format('Y-m-d'),
            'weekdays' => ['1', '3'],
            'time_from' => '10:00',
            'time_to' => '12:30',
            'duration' => '60',
            'gap' => '15',
        ])->assertSessionHasNoErrors();

        // 月・水 × 10:00, 11:15（12:30 を超える枠は作らない）
        $this->assertSame(4, InterviewSlot::query()->count());
        $this->assertTrue(InterviewSlot::query()->where('starts_at', $monday->copy()->setTime(11, 15))->exists());

        // 同じ条件で作り直しても重複しない
        $this->post('/admin/slots', [
            'date_from' => $monday->format('Y-m-d'), 'date_to' => $monday->format('Y-m-d'),
            'weekdays' => ['1'], 'time_from' => '10:00', 'time_to' => '11:00', 'duration' => '60',
        ]);
        $this->assertSame(4, InterviewSlot::query()->count());
        $this->get('/admin/slots')->assertOk()->assertSee('空き');
    }

    public function test_booked_slot_cannot_be_deleted(): void
    {
        $slot = $this->slot();
        $free = $this->slot(4);
        $this->passedApplicant();
        $this->post('/my/booking/reserve', ['slot_id' => $slot->id]);

        $this->actingAs($this->admin());
        $this->post('/admin/slots/'.$slot->id.'/delete')->assertSessionHasErrors('slot');
        $this->post('/admin/slots/'.$free->id.'/delete')->assertSessionHasNoErrors();
        $this->assertTrue(InterviewSlot::query()->whereKey($slot->id)->exists());
        $this->assertFalse(InterviewSlot::query()->whereKey($free->id)->exists());
    }

    public function test_google_oauth_checks_state_and_stores_refresh_token_encrypted(): void
    {
        config(['partner.google.client_id' => 'test-client', 'partner.google.client_secret' => 'test-secret']);
        $payload = rtrim(strtr(base64_encode(json_encode(['email' => 'recruit@example.com'])), '+/', '-_'), '=');
        Http::fake(['oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'at', 'refresh_token' => 'secret-refresh-token', 'expires_in' => 3600,
            'scope' => 'openid https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.freebusy email',
            'id_token' => 'header.'.$payload.'.signature',
        ])]);
        $this->actingAs($this->admin());

        // state がない・違う場合は受け付けない
        $this->get('/admin/google/callback?code=abc&state=forged')->assertRedirect('/admin/slots')->assertSessionHasErrors('google');
        Http::assertNothingSent();

        $redirect = $this->get('/admin/google/connect')->assertRedirect();
        $location = $redirect->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('offline', $query['access_type']);
        $this->assertStringContainsString('calendar.events', $query['scope']);

        $this->get('/admin/google/callback?code=abc&state='.$query['state'])->assertRedirect('/admin/slots')->assertSessionHasNoErrors();
        $stored = DB::table('app_settings')->where('key', 'google_refresh_token')->value('value');
        $this->assertStringNotContainsString('secret-refresh-token', $stored);
        $this->assertSame('secret-refresh-token', Crypt::decryptString($stored));
        $this->assertSame('recruit@example.com', AppSetting::get('google_account'));

        // 同じ state は再利用できない
        $this->get('/admin/google/callback?code=abc&state='.$query['state'])->assertSessionHasErrors('google');
    }

    public function test_booking_admin_pages_require_login(): void
    {
        $slot = $this->slot();
        $this->get('/admin/slots')->assertRedirect('/admin/login');
        $this->get('/admin/google/connect')->assertRedirect('/admin/login');
        $this->get('/admin/google/callback?code=x&state=y')->assertRedirect('/admin/login');
        $this->post('/admin/slots/'.$slot->id.'/delete')->assertRedirect('/admin/login');
        $this->assertTrue(InterviewSlot::query()->whereKey($slot->id)->exists());
    }
}
