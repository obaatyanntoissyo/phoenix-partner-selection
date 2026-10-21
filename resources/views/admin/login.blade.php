@extends('layouts.admin')
@section('title', 'ログイン')
@section('content')
    <h1>管理者ログイン</h1>
    <form method="post" action="{{ route('admin.login.attempt') }}" class="form narrow">
        @csrf
        <div class="field" id="f-email"><label for="email">メールアドレス</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required></div>
        <div class="field" id="f-password"><label for="password">パスワード</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
        <button type="submit" class="btn primary">ログイン</button>
    </form>
@endsection
