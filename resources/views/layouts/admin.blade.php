<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') | 選考管理</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="admin">
<header class="site-header">
    <div class="container header-row">
        <span>選考管理</span>
        @auth
            <nav class="admin-nav">
                <a href="{{ route('admin.dashboard') }}">ホーム</a>
                <a href="{{ route('admin.applications.index') }}">応募一覧</a>
                <a href="{{ route('admin.applications.create') }}">手動登録</a>
                <a href="{{ route('admin.settings') }}">設定</a>
                <a href="{{ route('admin.templates.index') }}">メール文面</a>
                <form method="post" action="{{ route('admin.logout') }}" class="inline-form">@csrf<button type="submit" class="link">ログアウト</button></form>
            </nav>
        @endauth
    </div>
</header>
<main class="container wide" id="main">
    @if (session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif
    @include('partials.errors')
    @yield('content')
</main>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
