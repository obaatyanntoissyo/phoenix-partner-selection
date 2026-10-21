@extends('layouts.admin')
@section('title', 'ホーム')
@section('content')
    <h1>ホーム</h1>
    <div class="stats">
        <a class="stat" href="{{ route('admin.applications.index', ['category' => 'p1']) }}"><span class="stat-label">①一括制作の応募</span><div class="stat-value">{{ $counts['p1'] }}<span class="stat-unit">件</span></div></a>
        <a class="stat" href="{{ route('admin.applications.index', ['status' => 'submitted']) }}"><span class="stat-label">見積提出済み</span><div class="stat-value">{{ $counts['submitted'] }}<span class="stat-unit">件</span></div></a>
        <a class="stat" href="{{ route('admin.applications.index', ['category' => 'p2']) }}"><span class="stat-label">②8341の応募</span><div class="stat-value">{{ $counts['p2'] }}<span class="stat-unit">件</span></div></a>
        <a class="stat" href="{{ route('admin.applications.index', ['mail' => 'failed']) }}"><span class="stat-label">送信に失敗したメール</span><div class="stat-value {{ $mailProblems ? 'warn' : '' }}">{{ $mailProblems }}<span class="stat-unit">件</span></div></a>
    </div>

    <section>
    <h2>本番公開前に設定が必要な項目</h2>
    @if ($missing)
        <ul class="missing">@foreach ($missing as $m)<li>{{ $m }}</li>@endforeach</ul>
        <p><a class="btn small-btn" href="{{ route('admin.settings') }}">設定へ</a></p>
    @else
        <p><span class="badge ok">OK</span> 未設定の項目はありません。</p>
    @endif
    </section>

    <section>
    <h2>最近の要対応通知</h2>
    @forelse ($alerts as $alert)
        <p class="small">{{ $alert->created_at->format('Y-m-d H:i') }}　{{ $alert->reason }}</p>
    @empty
        <p>ありません。</p>
    @endforelse
    </section>

    <section>
    <h2>保存期間に沿った削除</h2>
    @if ($retentionDays)
        <form method="post" action="{{ route('admin.purge') }}" class="form">
            @csrf
            <p>受付から {{ $retentionDays }} 日を過ぎた応募の個人情報を削除し、招待URLを失効させます。元に戻せません。</p>
            <label class="check"><input type="checkbox" name="confirm" value="1"> 削除を実行することを確認しました</label>
            <button type="submit" class="btn danger">削除を実行</button>
        </form>
    @else
        <p>保存期間が未設定のため、削除は実行できません（設定画面で指定してください）。</p>
    @endif
    </section>
@endsection
