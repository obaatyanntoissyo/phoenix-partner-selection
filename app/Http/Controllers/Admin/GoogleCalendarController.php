<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\GoogleCalendar;
use App\Services\GoogleCalendarException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** 面談予約を登録する Google アカウントとの連携（OAuth 2.0）。 */
class GoogleCalendarController extends Controller
{
    public function __construct(private GoogleCalendar $calendar) {}

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->calendar->hasClient()) {
            return redirect()->route('admin.slots.index')->withErrors(['google' => 'GOOGLE_CLIENT_ID と GOOGLE_CLIENT_SECRET が設定されていません（手順は docs/google-calendar.md）。']);
        }
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($this->calendar->authUrl($state, route('admin.google.callback')));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = (string) $request->session()->pull('google_oauth_state', '');
        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state', ''))) {
            return redirect()->route('admin.slots.index')->withErrors(['google' => '連携の確認に失敗しました。もう一度「Googleと連携する」からやり直してください。']);
        }
        if ($request->query('error') || ! is_string($request->query('code'))) {
            return redirect()->route('admin.slots.index')->withErrors(['google' => 'Google での許可がキャンセルされました。']);
        }

        try {
            $account = $this->calendar->connect($request->query('code'), route('admin.google.callback'), (int) $request->user()->id);
        } catch (GoogleCalendarException $e) {
            return redirect()->route('admin.slots.index')->withErrors(['google' => $e->getMessage()]);
        }
        AuditLog::record('google_connect', null, null, ['account' => $account]);

        return redirect()->route('admin.slots.index')->with('status', "Googleカレンダー（{$account}）と連携しました。");
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->calendar->disconnect((int) $request->user()->id);
        AuditLog::record('google_disconnect');

        return redirect()->route('admin.slots.index')->with('status', 'Googleカレンダーとの連携を解除しました。新しい予約はカレンダーに登録されません。');
    }
}
