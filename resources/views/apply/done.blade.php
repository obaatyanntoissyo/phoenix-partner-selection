@extends('layouts.app')
@section('title', '応募受付')
@section('content')
    <div class="result ok">
        <span class="result-icon" aria-hidden="true">✓</span>
        <h1>ご応募ありがとうございました</h1>
    </div>
    @if ($done)
        <div class="box"><p>応募番号：<strong>{{ $done['number'] }}</strong></p></div>
        @if ($done['category'] === 'p1' && $done['duplicate'])
            <p>以前にも同じメールアドレスでご応募いただいているため、今回の内容は担当者が確認いたします。追って担当者よりご連絡しますので、今しばらくお待ちください。</p>
        @elseif ($done['category'] === 'p1')
            <p>ご登録のメールアドレスに、見積入力の専用URLをお送りしました。メールをご確認ください。</p>
        @else
            <p>内容を確認のうえ、担当者より面談のご案内をお送りします。今しばらくお待ちください。</p>
        @endif
    @else
        <p>受付を完了しました。</p>
    @endif
@endsection
