@extends('layouts.app', ['step' => 'result'])
@section('title', '提出結果')
@section('content')
    <h1>見積のご提出ありがとうございました</h1>
    <p>応募番号：{{ $application->number }}</p>
    @if ($passed)
        <div class="box ok">
            <p>ご提示いただいた内容は、今回の面談案内条件を満たしています。次に面談日程をご登録ください。契約・発注の確定ではありません。</p>
        </div>
    @else
        <div class="box">
            <p>ご回答ありがとうございました。今回の募集条件とは合致しなかったため、面談のご案内を見送らせていただきます。</p>
        </div>
    @endif
    @if ($bookingAvailable)
        <p><a class="btn primary" href="{{ route('my.booking') }}">面談日程の登録へ進む</a></p>
    @endif
    @if ($canResubmit)
        <p>担当者から再提出のご案内がありました。<a href="{{ route('my.estimate') }}">見積を修正して再提出する</a></p>
    @endif
@endsection
