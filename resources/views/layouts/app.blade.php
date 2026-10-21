<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') | 一括制作パートナー募集 - デザインスタジオフェニックス</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <span class="brand-mark" aria-hidden="true">P</span>
        <span class="brand"><span class="brand-name">株式会社デザインスタジオフェニックス</span><span class="brand-sub">パートナー募集</span></span>
    </div>
</header>
<main class="container" id="main">
    @isset($step)
        @include('partials.steps', ['step' => $step])
    @endisset
    @if (session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif
    <div class="panel">
        @yield('content')
    </div>
</main>
<footer class="site-footer"><div class="container">株式会社デザインスタジオフェニックス</div></footer>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
