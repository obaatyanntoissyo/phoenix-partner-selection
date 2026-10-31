@extends('layouts.admin')
@section('title', 'ホーム')
@section('content')
    <h1>ホーム</h1>

    @if ($counts['held'] > 0)
        <a class="callout" href="{{ route('admin.applications.index', ['outcome' => 'held', 'sort' => 'total_asc']) }}">
            <span class="callout-icon" aria-hidden="true">!</span>
            <span><strong>結果の連絡待ちが {{ $counts['held'] }}件 あります。</strong><br>
                <span class="small">見積の金額が上限を超えた応募です。内容を見て、通過・不通過を決めて連絡してください。</span></span>
            <span class="callout-go" aria-hidden="true">開く →</span>
        </a>
    @endif
    @if ($mailProblems > 0)
        <a class="callout callout-err" href="{{ route('admin.applications.index', ['mail' => 'failed']) }}">
            <span class="callout-icon" aria-hidden="true">!</span>
            <span><strong>送信に失敗したメールが {{ $mailProblems }}件 あります。</strong><br>
                <span class="small">応募詳細の「記録」から再送できます。</span></span>
            <span class="callout-go" aria-hidden="true">開く →</span>
        </a>
    @endif

    <div class="stats">
        <a class="stat" href="{{ route('admin.applications.index', ['category' => 'p1']) }}"><span class="stat-label">①一括制作の応募</span><div class="stat-value">{{ $counts['p1'] }}<span class="stat-unit">件</span></div></a>
        <a class="stat" href="{{ route('admin.applications.index', ['status' => 'submitted']) }}"><span class="stat-label">見積を提出済み</span><div class="stat-value">{{ $counts['submitted'] }}<span class="stat-unit">件</span></div></a>
        <a class="stat" href="{{ route('admin.slots.index') }}"><span class="stat-label">これからの面談</span><div class="stat-value">{{ $counts['upcoming'] }}<span class="stat-unit">件</span></div></a>
        <a class="stat" href="{{ route('admin.applications.index', ['category' => 'p2']) }}"><span class="stat-label">②8341の応募</span><div class="stat-value">{{ $counts['p2'] }}<span class="stat-unit">件</span></div></a>
    </div>

    <div class="two-col">
        <div class="main-col">
            <section>
                <h2>これからの面談</h2>
                @forelse ($upcomingBookings as $b)
                    <p class="upcoming"><span class="when">{{ $b->slot->label() }}</span>
                        <a href="{{ route('admin.applications.show', $b->application) }}">{{ $b->application->name }}</a>
                        <span class="small">{{ $b->application->number }}</span></p>
                @empty
                    <p class="empty">予定されている面談はありません。</p>
                @endforelse
            </section>

            <section>
                <h2>最近の要対応通知</h2>
                @forelse ($alerts as $alert)
                    <p class="small">{{ $alert->created_at->format('Y-m-d H:i') }}　{{ $alert->reason }}</p>
                @empty
                    <p class="empty">ありません。</p>
                @endforelse
            </section>
        </div>

        <aside class="side-col">
            <section>
                <h2>公開前に設定が必要な項目</h2>
                @if ($missing)
                    <ul class="missing">@foreach ($missing as $m)<li>{{ $m }}</li>@endforeach</ul>
                    <p><a class="btn small-btn" href="{{ route('admin.settings') }}">設定を開く</a></p>
                @else
                    <p><span class="badge ok">OK</span> 未設定の項目はありません。</p>
                @endif
            </section>

            <section>
                <h2>古い個人情報の削除</h2>
                @if ($retentionDays)
                    <form method="post" action="{{ route('admin.purge') }}" class="form">
                        @csrf
                        <p class="small">受付から {{ $retentionDays }} 日を過ぎた応募の個人情報を削除し、見積ページのURLを無効にします。<strong>元には戻せません。</strong></p>
                        <label class="check"><input type="checkbox" name="confirm" value="1"> 削除を実行することを確認しました</label>
                        <button type="submit" class="btn danger small-btn">削除を実行</button>
                    </form>
                @else
                    <p class="small">保存期間が未設定のため、削除はできません。<a href="{{ route('admin.settings') }}">設定</a>で保存期間を決めてください。</p>
                @endif
            </section>
        </aside>
    </div>
@endsection
