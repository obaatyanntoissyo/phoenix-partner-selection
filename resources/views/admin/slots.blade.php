@extends('layouts.admin')
@section('title', '面談日程')
@section('content')
    @php($CAL = \App\Models\InterviewBooking::CALENDAR_LABELS)
    @php($booked = $slots->filter(fn ($s) => $s->activeBooking && $s->starts_at->isFuture()))
    @php($freeCount = $slots->filter(fn ($s) => ! $s->activeBooking && $s->starts_at->isFuture())->count())
    @php($days = $slots->groupBy(fn ($s) => $s->starts_at->format('Y-m-d')))
    <h1>面談日程</h1>
    @if ($mode !== 'slots')
        <p class="warn">いまの予約の方法は「このシステムで空き枠を選ぶ」ではないため、ここで作った空き枠は応募者に表示されません。<a href="{{ route('admin.settings') }}#general">設定</a>で切り替えてください。</p>
    @endif

    <div class="stats">
        <div class="stat">
            <span class="stat-label">Googleカレンダー</span>
            <div class="stat-text">
                @if (! $google['client'])
                    <span class="badge warn">未設定</span>
                @elseif ($google['connected'])
                    <span class="badge ok">連携中</span>
                @else
                    <span class="badge warn">未連携</span>
                @endif
            </div>
            @if ($google['connected'])<span class="small">{{ $google['account'] }}</span>@endif
        </div>
        <div class="stat"><span class="stat-label">予約が入っている面談</span><div class="stat-value">{{ $booked->count() }}<span class="stat-unit">件</span></div></div>
        <div class="stat"><span class="stat-label">空いている枠</span><div class="stat-value">{{ $freeCount }}<span class="stat-unit">枠</span></div></div>
    </div>

    @if ($problems->isNotEmpty())
        <section>
            <h2 class="warn">カレンダーに反映できていない予約</h2>
            <table class="list">
                <thead><tr><th>応募者</th><th>日時</th><th>予約</th><th>状態</th><th>エラー</th><th></th></tr></thead>
                <tbody>
                @foreach ($problems as $b)
                    <tr>
                        <td><a href="{{ route('admin.applications.show', $b->application_id) }}">{{ $b->application?->name }}</a><br><span class="small">{{ $b->application?->number }}</span></td>
                        <td>{{ $b->slot?->label() ?? '（削除済みの枠）' }}</td>
                        <td>{{ $b->isActive() ? '有効' : '取り消し' }}</td>
                        <td>{{ $CAL[$b->calendar_state] ?? $b->calendar_state }}（{{ $b->calendar_attempts }}回）</td>
                        <td class="small">{{ $b->calendar_error }}</td>
                        <td><form method="post" action="{{ route('admin.bookings.retry', $b) }}">@csrf<button type="submit" class="btn small-btn">もう一度試す</button></form></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <section>
        <h2>予約が入っている面談</h2>
        @if ($booked->isEmpty())
            <p class="empty">予約はまだありません。</p>
        @else
            <table class="list">
                <thead><tr><th>日時</th><th>応募者</th><th>カレンダー</th><th></th></tr></thead>
                <tbody>
                @foreach ($booked as $slot)
                    @php($b = $slot->activeBooking)
                    <tr>
                        <td><strong>{{ $slot->label() }}</strong></td>
                        <td><a href="{{ route('admin.applications.show', $b->application_id) }}">{{ $b->application?->name }}</a><br><span class="small">{{ $b->application?->number }}</span></td>
                        <td>
                            <span @class(['badge', 'ok' => $b->calendar_state === 'synced', 'warn' => in_array($b->calendar_state, ['pending', 'skipped'], true), 'err' => $b->calendar_state === 'dead'])>{{ $CAL[$b->calendar_state] ?? $b->calendar_state }}</span>
                            @if ($b->meet_url)<br><a class="small" href="{{ $b->meet_url }}" target="_blank" rel="noopener noreferrer">Meet を開く</a>@endif
                        </td>
                        <td>
                            <details>
                                <summary>予約を取り消す</summary>
                                <form method="post" action="{{ route('admin.bookings.cancel', $b) }}" class="form">
                                    @csrf
                                    <label>メモ（社内用） <input name="reason" maxlength="500" required></label>
                                    <label class="check"><input type="checkbox" name="notify" value="1" checked> 応募者に取り消しのメールを送る</label>
                                    <button type="submit" class="btn danger small-btn">取り消す</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section>
        <h2>空き枠をまとめて作る</h2>
        <form method="post" action="{{ route('admin.slots.store') }}" class="form">
            @csrf
            <div class="row">
                <label>期間 <input id="f-date_from" type="date" name="date_from" value="{{ old('date_from', today()->addDay()->format('Y-m-d')) }}" required></label>
                <label>〜 <input id="f-date_to" type="date" name="date_to" value="{{ old('date_to', today()->addDays(14)->format('Y-m-d')) }}" required></label>
            </div>
            <fieldset class="field" id="f-weekdays">
                <legend>曜日</legend>
                @foreach (['日', '月', '火', '水', '木', '金', '土'] as $i => $w)
                    <label class="check inline"><input type="checkbox" name="weekdays[]" value="{{ $i }}" @checked(in_array($i, old('weekdays', [1, 2, 3, 4, 5])))> {{ $w }}</label>
                @endforeach
            </fieldset>
            <div class="row">
                <label>時間帯 <input id="f-time_from" type="time" name="time_from" value="{{ old('time_from', '10:00') }}" required></label>
                <label>〜 <input id="f-time_to" type="time" name="time_to" value="{{ old('time_to', '17:00') }}" required></label>
                <label>1枠の長さ
                    <select name="duration">@foreach ([15, 30, 45, 60, 90, 120] as $m)<option value="{{ $m }}" @selected((int) old('duration', 60) === $m)>{{ $m }}分</option>@endforeach</select></label>
                <label>枠と枠の間隔
                    <select name="gap">@foreach ([0, 10, 15, 30, 60] as $m)<option value="{{ $m }}" @selected((int) old('gap', 0) === $m)>{{ $m }}分</option>@endforeach</select></label>
            </div>
            <p class="small">例：来月の平日の10時〜17時を、1枠60分でまとめて作れます。すでにある枠と同じ時刻の枠は作りません。</p>
            <button type="submit" class="btn primary">空き枠を作る</button>
        </form>
    </section>

    <section>
        <h2>日ごとの枠（7日前以降）</h2>
        @if ($slots->isEmpty())
            <p class="empty">枠がありません。上の「空き枠をまとめて作る」から作ってください。</p>
        @else
            @foreach ($days as $date => $daySlots)
                @php($day = $daySlots->first()->starts_at)
                @php($dayBooked = $daySlots->filter(fn ($s) => $s->activeBooking)->count())
                <details class="day" @open($loop->index < 3 && $day->isFuture())>
                    <summary><span class="day-name">{{ $day->isoFormat('M月D日(ddd)') }}</span>
                        <span class="small">全{{ $daySlots->count() }}枠・予約 {{ $dayBooked }}・空き {{ $daySlots->count() - $dayBooked }}</span></summary>
                    <ul class="slot-list">
                        @foreach ($daySlots as $slot)
                            @php($b = $slot->activeBooking)
                            <li>
                                <span class="slot-time">{{ $slot->starts_at->format('H:i') }}〜{{ $slot->ends_at->format('H:i') }}</span>
                                @if ($b)
                                    <span class="badge ok">予約済み</span> <a href="{{ route('admin.applications.show', $b->application_id) }}">{{ $b->application?->name }}</a>
                                @else
                                    <span class="badge ng">空き</span>
                                    @if ($slot->starts_at->isFuture())
                                        <form method="post" action="{{ route('admin.slots.destroy', $slot) }}" class="inline-form">@csrf<button type="submit" class="link">この枠を削除</button></form>
                                    @endif
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        @endif
    </section>

    <section>
        <h2>Googleカレンダーとの連携</h2>
        @if (! $google['client'])
            <p>Google Cloud の OAuth クライアントが未設定です。<code>.env</code> に <code>GOOGLE_CLIENT_ID</code> と <code>GOOGLE_CLIENT_SECRET</code> を設定してください（手順：docs/google-calendar.md）。リダイレクトURIには次を登録します。</p>
            <p><code>{{ $google['redirect_uri'] }}</code></p>
            <p class="small">連携しなくても予約は受け付けられますが、Googleカレンダーへの登録と、カレンダーの予定との重なりの確認は行われません。</p>
        @elseif ($google['connected'])
            <p>連携中：<strong>{{ $google['account'] }}</strong>。予約が入ると、このアカウントのカレンダーに予定を作り、応募者を招待します。</p>
            <form method="post" action="{{ route('admin.google.disconnect') }}" class="inline-form">@csrf<button type="submit" class="btn small-btn">連携を解除する</button></form>
        @else
            <p>まだ連携していません。面談の予定を入れたいGoogleアカウントでログインして、許可してください。</p>
            <p><a class="btn primary" href="{{ route('admin.google.connect') }}">Googleと連携する</a></p>
            <p class="small">リダイレクトURI：<code>{{ $google['redirect_uri'] }}</code></p>
        @endif

        <details class="fold">
            <summary>予約の締切・カレンダーなどの設定</summary>
            <form method="post" action="{{ route('admin.slots.settings') }}" class="form">
                @csrf
                <div class="row">
                    <label>予約の締切（開始の何時間前まで） <input id="f-booking_lead_hours" name="booking_lead_hours" type="number" min="0" max="720" value="{{ old('booking_lead_hours', $settings['booking_lead_hours'] ?? 24) }}" required></label>
                    <label>カレンダーID <input id="f-google_calendar_id" name="google_calendar_id" value="{{ old('google_calendar_id', $settings['google_calendar_id'] ?? 'primary') }}" required></label>
                    <label class="check"><input type="checkbox" name="google_meet" value="1" @checked(($settings['google_meet'] ?? '1') === '1')> Google Meet のリンクを付ける</label>
                </div>
                <p class="small">カレンダーIDは、ふつうは「primary」（連携したアカウントのメインのカレンダー）のままで大丈夫です。</p>
                <button type="submit" class="btn small-btn">保存</button>
            </form>
        </details>
    </section>

    @if ($cancelled->isNotEmpty())
        <section>
            <h2>取り消した予約（最近20件）</h2>
            <ul class="small log">
                @foreach ($cancelled as $b)
                    <li>{{ $b->cancelled_at?->format('Y-m-d H:i') }}　{{ $b->application?->name }}（{{ $b->application?->number }}）　{{ $b->slot?->label() ?? '（削除済みの枠）' }}　メモ：{{ $b->cancel_reason }}</li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
