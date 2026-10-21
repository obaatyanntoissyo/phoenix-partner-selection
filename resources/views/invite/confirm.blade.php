@extends('layouts.app')
@section('title', '専用ページへの入室')
@section('content')
    <h1>{{ $application->name }} 様の専用ページ</h1>
    <p>応募番号：{{ $application->number }}</p>
    <p>下のボタンを押すと、募集条件の確認と見積入力のページへ進みます。</p>
    <p class="hint">このURLはご本人専用です。第三者へ渡さないでください。</p>
    <form method="post" action="{{ route('invite.accept') }}" data-once>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <button type="submit" class="btn primary">専用ページへ進む</button>
    </form>
@endsection
