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
    <div class="header-inner">
        <span class="brand-wrap">
            <span class="brand-mark" aria-hidden="true">P</span>
            <span class="brand"><span class="brand-name">選考管理</span><span class="brand-sub">デザインスタジオフェニックス</span></span>
        </span>
        @auth
            <nav class="admin-nav" aria-label="管理メニュー">
                @foreach (['admin.dashboard' => 'ホーム', 'admin.applications.index' => '応募一覧', 'admin.applications.create' => '手動登録', 'admin.slots.index' => '面談日程', 'admin.settings' => '設定', 'admin.templates.index' => 'メール文面'] as $route => $label)
                    @php($active = request()->routeIs($route) || ($route === 'admin.applications.index' && request()->routeIs('admin.applications.show')) || ($route === 'admin.templates.index' && request()->routeIs('admin.templates.*')))
                    <a href="{{ route($route) }}" @class(['active' => $active]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
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
