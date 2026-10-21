<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Google カレンダー API（OAuth 2.0・利用者本人のアカウントで認可）。
 * 外部ライブラリを使わず、必要な API だけを HTTP で呼ぶ。リフレッシュトークンは暗号化して保存する。
 */
class GoogleCalendar
{
    public const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.freebusy',
    ];

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API = 'https://www.googleapis.com/calendar/v3';

    public function hasClient(): bool
    {
        return (bool) (config('partner.google.client_id') && config('partner.google.client_secret'));
    }

    public function isConnected(): bool
    {
        return $this->hasClient() && AppSetting::get('google_refresh_token') !== null;
    }

    public function authUrl(string $state, string $redirectUri): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('partner.google.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /** 認可コードをトークンに交換し、リフレッシュトークンを保存する。連携したアカウントのメールアドレスを返す。 */
    public function connect(string $code, string $redirectUri, ?int $adminId): string
    {
        $response = $this->send(fn () => Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('partner.google.client_id'),
            'client_secret' => config('partner.google.client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]));
        $refresh = $response->json('refresh_token');
        if (! is_string($refresh) || $refresh === '') {
            throw new GoogleCalendarException('リフレッシュトークンを取得できませんでした');
        }
        $granted = explode(' ', (string) $response->json('scope'));
        foreach (['https://www.googleapis.com/auth/calendar.events', 'https://www.googleapis.com/auth/calendar.freebusy'] as $scope) {
            if (! in_array($scope, $granted, true)) {
                throw new GoogleCalendarException('カレンダーへのアクセスが許可されませんでした（Googleの画面ですべての項目にチェックを入れてください）');
            }
        }
        // id_token はトークンエンドポイントから TLS で直接受け取ったものなので、表示用のメールアドレスだけ読む
        $email = $this->emailFromIdToken((string) $response->json('id_token'));

        AppSetting::put('google_refresh_token', Crypt::encryptString($refresh), $adminId);
        AppSetting::put('google_account', $email, $adminId);
        Cache::forget($this->tokenCacheKey());

        return $email;
    }

    public function disconnect(?int $adminId): void
    {
        $token = $this->refreshToken();
        if ($token) {
            // Google 側の許可も取り消す（失敗しても連携情報は消す）
            try {
                Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
            } catch (ConnectionException) {
            }
        }
        AppSetting::put('google_refresh_token', null, $adminId);
        AppSetting::put('google_account', null, $adminId);
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * 指定期間の「予定あり」時間帯を返す。
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    public function busy(CarbonInterface $from, CarbonInterface $to): array
    {
        $calendarId = $this->calendarId();
        $response = $this->send(fn () => $this->api()->post(self::API.'/freeBusy', [
            'timeMin' => $from->toRfc3339String(),
            'timeMax' => $to->toRfc3339String(),
            'timeZone' => config('app.timezone'),
            'items' => [['id' => $calendarId]],
        ]));
        if ($response->json("calendars.{$calendarId}.errors")) {
            throw new GoogleCalendarException('カレンダーの空き状況を取得できませんでした（カレンダーIDを確認してください）');
        }

        return array_map(fn ($b) => [
            Carbon::parse($b['start'])->setTimezone(config('app.timezone')),
            Carbon::parse($b['end'])->setTimezone(config('app.timezone')),
        ], (array) $response->json("calendars.{$calendarId}.busy", []));
    }

    /**
     * 予定を作る。ID はこちらで決めたものを使うため、同じ予約で再試行しても重複しない（409 は作成済みとみなす）。
     *
     * @return array{meet_url: ?string}
     */
    public function insertEvent(string $eventId, string $summary, string $description, CarbonInterface $start, CarbonInterface $end, string $attendeeEmail, bool $withMeet): array
    {
        $body = [
            'id' => $eventId,
            'summary' => $summary,
            'description' => $description,
            'start' => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => config('app.timezone')],
            'end' => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => config('app.timezone')],
            'attendees' => [['email' => $attendeeEmail]],
            'guestsCanInviteOthers' => false,
            'guestsCanSeeOtherGuests' => false,
        ];
        if ($withMeet) {
            $body['conferenceData'] = ['createRequest' => ['requestId' => $eventId, 'conferenceSolutionKey' => ['type' => 'hangoutsMeet']]];
        }
        $url = self::API.'/calendars/'.rawurlencode($this->calendarId()).'/events?'.http_build_query(['conferenceDataVersion' => $withMeet ? 1 : 0, 'sendUpdates' => 'all']);
        $response = $this->send(fn () => $this->api()->post($url, $body), [409]);
        if ($response->status() === 409) {
            $response = $this->send(fn () => $this->api()->get($this->eventUrl($eventId)));
        }

        return ['meet_url' => $response->json('hangoutLink')];
    }

    /** 予定を削除する。すでに無い予定（404/410）は削除済みとみなす。 */
    public function deleteEvent(string $eventId): void
    {
        $this->send(fn () => $this->api()->delete($this->eventUrl($eventId).'?sendUpdates=all'), [404, 410]);
    }

    private function eventUrl(string $eventId): string
    {
        return self::API.'/calendars/'.rawurlencode($this->calendarId()).'/events/'.rawurlencode($eventId);
    }

    private function calendarId(): string
    {
        return (string) AppSetting::get('google_calendar_id', 'primary');
    }

    private function api(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(15);
    }

    private function accessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());
        if (is_string($cached) && $cached !== '') {
            return Crypt::decryptString($cached);
        }
        $refresh = $this->refreshToken();
        if (! $refresh || ! $this->hasClient()) {
            throw new GoogleCalendarException('Googleカレンダーと連携していません');
        }
        $response = $this->send(fn () => Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'client_id' => config('partner.google.client_id'),
            'client_secret' => config('partner.google.client_secret'),
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ]));
        $token = (string) $response->json('access_token');
        $ttl = max(60, (int) $response->json('expires_in', 3600) - 120);
        Cache::put($this->tokenCacheKey(), Crypt::encryptString($token), $ttl);

        return $token;
    }

    private function refreshToken(): ?string
    {
        $stored = AppSetting::get('google_refresh_token');

        return $stored ? Crypt::decryptString($stored) : null;
    }

    private function tokenCacheKey(): string
    {
        return 'google-calendar-access-token';
    }

    /** 通信・HTTP エラーを GoogleCalendarException に揃える。応答本文は個人情報を含みうるため残さない。 */
    private function send(callable $request, array $okStatuses = []): Response
    {
        try {
            /** @var Response $response */
            $response = $request();
        } catch (ConnectionException) {
            throw new GoogleCalendarException('Googleに接続できませんでした');
        }
        if ($response->successful() || in_array($response->status(), $okStatuses, true)) {
            return $response;
        }
        if ($response->status() === 401 || $response->json('error') === 'invalid_grant') {
            Cache::forget($this->tokenCacheKey());
            throw new GoogleCalendarException('Googleの認証が無効です。管理画面から連携し直してください', $response->status());
        }
        $reason = $response->json('error.errors.0.reason') ?? $response->json('error');

        throw new GoogleCalendarException('Google API エラー（HTTP '.$response->status().(is_string($reason) ? ' '.$reason : '').'）', $response->status());
    }

    private function emailFromIdToken(string $idToken): string
    {
        $parts = explode('.', $idToken);
        $payload = isset($parts[1]) ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true) : null;

        return is_array($payload) && is_string($payload['email'] ?? null) ? $payload['email'] : '（不明なアカウント）';
    }
}
