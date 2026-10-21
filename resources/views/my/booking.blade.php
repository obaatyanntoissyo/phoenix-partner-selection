@extends('layouts.app', ['step' => 'booking'])
@section('title', $mode === 'form' ? '面談希望日時の登録' : '面談日程の予約')
@section('content')
    <h1>{{ $mode === 'form' ? '面談希望日時の登録' : '面談日程の予約' }}</h1>
    <p>下のボタンから外部の{{ $mode === 'form' ? '登録フォーム' : '予約ページ' }}へ移動します。</p>
    <div class="box">
        <p>登録の際は、<strong>応募番号「{{ $application->number }}」</strong>をご記入ください。</p>
        @if ($mode === 'form')
            <p>このフォームは面談の<strong>希望日時</strong>を伺うものです。日程は担当者が確認のうえ、改めてご連絡します。</p>
        @else
            <p>予約ページで表示される空き枠から日程をお選びください。</p>
        @endif
        <p class="hint">このページと予約先はご本人専用です。第三者へ渡さないでください。</p>
    </div>
    @if ($interview->booking_status === 'confirmed')
        <p>面談日程は確認済みです{{ $interview->interview_at ? '（'.$interview->interview_at->format('Y年n月j日 H:i').'）' : '' }}。</p>
    @endif
    <form method="post" action="{{ route('my.booking.go') }}">
        @csrf
        <button type="submit" class="btn primary">{{ $mode === 'form' ? '希望日時の登録フォームへ移動' : '予約ページへ移動' }}</button>
    </form>
@endsection
