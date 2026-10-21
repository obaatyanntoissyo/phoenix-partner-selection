@extends('layouts.app')
@section('title', '応募受付')
@section('content')
    <h1>ご応募ありがとうございました</h1>
    @if ($done)
        <p>応募番号：<strong>{{ $done['number'] }}</strong></p>
        @if ($done['category'] === 'p1' && $done['duplicate'])
            <p>同じメールアドレスでのご応募を受け付けています。今回の内容も記録しました。担当者からのご連絡をお待ちください。</p>
        @elseif ($done['category'] === 'p1')
            <p>ご登録のメールアドレスに、見積入力の専用URLをお送りしました。メールをご確認ください。</p>
        @else
            <p>内容を確認のうえ、担当者より面談のご案内をお送りします。今しばらくお待ちください。</p>
        @endif
    @else
        <p>受付を完了しました。</p>
    @endif
@endsection
