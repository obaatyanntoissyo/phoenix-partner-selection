@extends('layouts.app', ['step' => 'booking'])
@section('title', '面談日程の予約')
@section('content')
    <h1>面談日程の予約</h1>
    <p class="meta"><span>応募番号：{{ $application->number }}</span></p>
    @include('partials.errors')

    @if ($booking)
        <div class="box ok result ok">
            <span class="result-icon" aria-hidden="true">✓</span>
            <div>
            <p class="result-title">ご予約を承りました</p>
            <p class="booking-date">{{ $booking->slot->label() }}</p>
            <p>オンラインで行います。確認のメールをお送りしました。</p>
            @if ($booking->meet_url)
                <p>参加URL：<a href="{{ $booking->meet_url }}" rel="noopener noreferrer" target="_blank">{{ $booking->meet_url }}</a></p>
            @else
                <p>オンライン面談の参加URLは、別途メールでお送りします。</p>
            @endif
            </div>
        </div>
        <p>日程の変更をご希望の場合は、受付メールに記載の問い合わせ先までご連絡ください。</p>
    @elseif ($slots->isEmpty())
        <div class="box">
            <p>現在、予約できる日時がありません。日程を追加しましたら、担当者よりご連絡いたします。</p>
        </div>
    @else
        <p>ご都合のよい日時を1つ選んで「この日時で予約する」を押してください。面談はオンラインで行います。</p>
        <form method="post" action="{{ route('my.booking.reserve') }}" class="form">
            @csrf
            @foreach ($slots->groupBy(fn ($s) => $s->starts_at->format('Y-m-d')) as $date => $daySlots)
                <fieldset class="slot-day">
                    <legend>{{ \Illuminate\Support\Str::before($daySlots->first()->label(), ' ') }}</legend>
                    <div class="slot-options">
                    @foreach ($daySlots as $slot)
                        <label class="slot"><input type="radio" name="slot_id" value="{{ $slot->id }}" required @checked((string) old('slot_id') === (string) $slot->id)><span>{{ $slot->starts_at->format('H:i') }}〜{{ $slot->ends_at->format('H:i') }}</span></label>
                    @endforeach
                    </div>
                </fieldset>
            @endforeach
            <p class="hint">予約後の変更は、担当者へのご連絡が必要です。面談は契約・発注をお約束するものではありません。</p>
            <div class="actions"><button type="submit" class="btn primary">この日時で予約する</button></div>
        </form>
    @endif
@endsection
