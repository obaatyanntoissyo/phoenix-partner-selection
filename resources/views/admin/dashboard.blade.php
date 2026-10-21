@extends('layouts.admin')
@section('title', 'ホーム')
@section('content')
    <h1>ホーム</h1>
    <p>①一括制作 {{ $counts['p1'] }}件 ／ ②8341 {{ $counts['p2'] }}件 ／ 見積提出済み {{ $counts['submitted'] }}件</p>
    @if ($mailProblems)
        <p class="warn"><a href="{{ route('admin.applications.index', ['mail' => 'failed']) }}">送信に失敗したメールが {{ $mailProblems }} 件あります</a></p>
    @endif

    <h2>本番公開前に設定が必要な項目</h2>
    @if ($missing)
        <ul class="missing">@foreach ($missing as $m)<li>{{ $m }}</li>@endforeach</ul>
        <p><a href="{{ route('admin.settings') }}">設定へ</a></p>
    @else
        <p>未設定の項目はありません。</p>
    @endif

    <h2>最近の要対応通知</h2>
    @forelse ($alerts as $alert)
        <p class="small">{{ $alert->created_at->format('Y-m-d H:i') }}　{{ $alert->reason }}</p>
    @empty
        <p>ありません。</p>
    @endforelse

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
@endsection
