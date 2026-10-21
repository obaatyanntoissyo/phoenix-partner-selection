@extends('layouts.admin')
@section('title', '面談日程')
@section('content')
    <h1>面談日程</h1>
    @if ($mode !== 'slots')
        <p class="warn">現在の「予約先の種類」は「このシステムで空き枠を選ぶ」ではないため、ここで作った枠は応募者に表示されません。<a href="{{ route('admin.settings') }}">設定</a>で切り替えてください。</p>
    @endif

    <section>
        <h2>Googleカレンダー連携</h2>
        @if (! $google['client'])
            <p>Google Cloud の OAuth クライアントが未設定です。<code>.env</code> に <code>GOOGLE_CLIENT_ID</code> と <code>GOOGLE_CLIENT_SECRET</code> を設定してください（手順：docs/google-calendar.md）。リダイレクトURIには次を登録します。</p>
            <p><code>{{ $google['redirect_uri'] }}</code></p>
            <p class="small">連携しなくても予約は受け付けられますが、Googleカレンダーへの登録と、カレンダーの予定との重なりの確認は行われません。</p>
        @elseif ($google['connected'])
            <p>連携中：<strong>{{ $google['account'] }}</strong>。予約が入ると、このアカウントのカレンダーに予定（応募者を招待）を作成します。</p>
            <form method="post" action="{{ route('admin.google.disconnect') }}" class="inline-form">@csrf<button type="submit" class="btn">連携を解除</button></form>
        @else
            <p>未連携です。面談の予定を入れたい Google アカウントでログインして許可してください。</p>
            <p><a class="btn primary" href="{{ route('admin.google.connect') }}">Googleと連携する</a></p>
            <p class="small">リダイレクトURI：<code>{{ $google['redirect_uri'] }}</code></p>
        @endif

        <form method="post" action="{{ route('admin.slots.settings') }}" class="form">
            @csrf
            <div class="row">
                <label>予約の締切（開始の何時間前まで） <input id="f-booking_lead_hours" name="booking_lead_hours" type="number" min="0" max="720" value="{{ old('booking_lead_hours', $settings['booking_lead_hours'] ?? 24) }}" required></label>
                <label>カレンダーID <input id="f-google_calendar_id" name="google_calendar_id" value="{{ old('google_calendar_id', $settings['google_calendar_id'] ?? 'primary') }}" required></label>
                <label class="check"><input type="checkbox" name="google_meet" value="1" @checked(($settings['google_meet'] ?? '1') === '1')> Google Meet のリンクを付ける</label>
            </div>
            <p class="small">カレンダーIDは通常「primary」（連携したアカウントのメインのカレンダー）のままで構いません。</p>
            <button type="submit" class="btn">保存</button>
        </form>
    </section>

    @if ($problems->isNotEmpty())
        <section>
            <h2 class="warn">カレンダーに反映できていない予約</h2>
            <table class="list">
                <thead><tr><th>応募番号</th><th>日時</th><th>予約</th><th>状態</th><th>エラー</th><th></th></tr></thead>
                <tbody>
                @foreach ($problems as $b)
                    <tr>
                        <td><a href="{{ route('admin.applications.show', $b->application_id) }}">{{ $b->application?->number }}</a></td>
                        <td>{{ $b->slot?->label() ?? '（削除済みの枠）' }}</td>
                        <td>{{ $b->isActive() ? '有効' : '取り消し' }}</td>
                        <td>{{ \App\Models\InterviewBooking::CALENDAR_LABELS[$b->calendar_state] ?? $b->calendar_state }}（{{ $b->calendar_attempts }}回）</td>
                        <td class="small">{{ $b->calendar_error }}</td>
                        <td><form method="post" action="{{ route('admin.bookings.retry', $b) }}">@csrf<button type="submit" class="btn">再試行</button></form></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <section>
        <h2>空き枠を追加</h2>
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
                <label>枠の間隔
                    <select name="gap">@foreach ([0, 10, 15, 30, 60] as $m)<option value="{{ $m }}" @selected((int) old('gap', 0) === $m)>{{ $m }}分</option>@endforeach</select></label>
            </div>
            <button type="submit" class="btn primary">空き枠を作成</button>
        </form>
    </section>

    <section>
        <h2>枠と予約（7日前以降）</h2>
        @if ($slots->isEmpty())
            <p>枠がありません。</p>
        @else
            <table class="list">
                <thead><tr><th>日時</th><th>予約</th><th>カレンダー</th><th>操作</th></tr></thead>
                <tbody>
                @foreach ($slots as $slot)
                    @php($b = $slot->activeBooking)
                    <tr>
                        <td>{{ $slot->label() }}</td>
                        <td>
                            @if ($b)
                                <a href="{{ route('admin.applications.show', $b->application_id) }}">{{ $b->application?->number }}</a>
                            @else
                                空き
                            @endif
                        </td>
                        <td>{{ $b ? (\App\Models\InterviewBooking::CALENDAR_LABELS[$b->calendar_state] ?? $b->calendar_state) : '' }}</td>
                        <td>
                            @if ($b)
                                <details>
                                    <summary>予約を取り消す</summary>
                                    <form method="post" action="{{ route('admin.bookings.cancel', $b) }}" class="form">
                                        @csrf
                                        <label>理由（社内記録） <input name="reason" maxlength="500" required></label>
                                        <label class="check"><input type="checkbox" name="notify" value="1" checked> 応募者に取り消しのメールを送る</label>
                                        <button type="submit" class="btn danger">取り消す</button>
                                    </form>
                                </details>
                            @elseif ($slot->starts_at->isFuture())
                                <form method="post" action="{{ route('admin.slots.destroy', $slot) }}">@csrf<button type="submit" class="btn">削除</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @if ($cancelled->isNotEmpty())
        <section>
            <h2>取り消した予約（最近20件）</h2>
            <ul class="small">
                @foreach ($cancelled as $b)
                    <li>{{ $b->cancelled_at?->format('Y-m-d H:i') }}　{{ $b->application?->number }}　{{ $b->slot?->label() ?? '（削除済みの枠）' }}　理由：{{ $b->cancel_reason }}</li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
