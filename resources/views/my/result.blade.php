@extends('layouts.app', ['step' => 'result'])
@section('title', '提出結果')
@section('content')
    <h1>見積のご提出ありがとうございました</h1>
    <p class="meta"><span>応募番号：{{ $application->number }}</span></p>
    @if ($passed)
        <div class="box ok result ok">
            <span class="result-icon" aria-hidden="true">✓</span>
            <div>
                <p class="result-title">面談のご案内に進めます</p>
                <p>ご提示いただいた内容は、今回の面談案内条件を満たしています。次に面談日程をご登録ください。契約・発注の確定ではありません。</p>
            </div>
        </div>
    @elseif ($held)
        <div class="box result neutral">
            <span class="result-icon" aria-hidden="true">i</span>
            <div>
                <p class="result-title">選考結果は、改めてメールでお送りします</p>
                <p>お見積もりをご提出いただき、ありがとうございました。内容を確認のうえ、担当者より選考結果をメールでご連絡します。今しばらくお待ちください。</p>
            </div>
        </div>
    @else
        <div class="box result neutral">
            <span class="result-icon" aria-hidden="true">i</span>
            <div>
                <p class="result-title">ご回答ありがとうございました</p>
                <p>今回の募集条件とは合致しなかったため、面談のご案内を見送らせていただきます。</p>
            </div>
        </div>
    @endif
    @if ($bookingAvailable)
        <div class="actions"><a class="btn primary" href="{{ route('my.booking') }}">面談日程の登録へ進む</a></div>
    @endif
    @if ($canResubmit)
        <p>担当者から再提出のご案内がありました。<a href="{{ route('my.estimate') }}">見積を修正して再提出する</a></p>
    @endif
@endsection
