@extends('layouts.admin')
@section('title', '応募一覧')
@section('content')
    @php($A = \App\Models\Application::class)
    @php($R = \App\Models\ScreeningResult::class)
    @php($outcome = $filters['outcome'] ?? '')
    @php($onlyTab = collect($filters)->except(['outcome', 'sort'])->filter()->isEmpty())
    <div class="header-row page-head-row">
        <h1>応募一覧</h1>
        <div class="actions">
            <a class="btn small-btn" href="{{ route('admin.applications.create') }}">＋ 応募を手動で登録</a>
            <a class="btn small-btn" href="{{ route('admin.applications.export', $filters) }}">CSVで書き出す</a>
        </div>
    </div>

    <nav class="tabs" aria-label="判定で絞り込み">
        <a href="{{ route('admin.applications.index') }}" @class(['active' => $outcome === '' && $onlyTab])>すべて <span class="count">{{ $tabCounts['all'] }}</span></a>
        <a href="{{ route('admin.applications.index', ['outcome' => 'held', 'sort' => 'total_asc']) }}" @class(['active' => $outcome === 'held'])>結果待ち（保留）<span @class(['count', 'count-warn' => $tabCounts['held'] > 0])>{{ $tabCounts['held'] }}</span></a>
        <a href="{{ route('admin.applications.index', ['outcome' => 'passed']) }}" @class(['active' => $outcome === 'passed'])>通過 <span class="count">{{ $tabCounts['passed'] }}</span></a>
        <a href="{{ route('admin.applications.index', ['outcome' => 'failed']) }}" @class(['active' => $outcome === 'failed'])>不通過 <span class="count">{{ $tabCounts['failed'] }}</span></a>
    </nav>

    <form method="get" class="filters">
        @if ($outcome !== '')<input type="hidden" name="outcome" value="{{ $outcome }}">@endif
        <label class="search">氏名・会社・番号で探す <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="氏名・会社名・応募番号の一部"></label>
        <label>区分 <select name="category"><option value="">すべて</option>
            @foreach ($A::CATEGORY_LABELS as $k => $v)<option value="{{ $k }}" @selected(($filters['category'] ?? '') === $k)>{{ $v }}</option>@endforeach
        </select></label>
        <label>状態 <select name="status"><option value="">すべて</option>
            @foreach ($A::STATUS_LABELS as $k => $v)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $v }}</option>@endforeach
        </select></label>
        <label>メール <select name="mail"><option value="">すべて</option>
            <option value="failed" @selected(($filters['mail'] ?? '') === 'failed')>送信に失敗したもの</option>
            <option value="pending" @selected(($filters['mail'] ?? '') === 'pending')>未送信のもの</option>
        </select></label>
        <label>並び順 <select name="sort">
            <option value="">新しい順</option>
            <option value="total_asc" @selected(($filters['sort'] ?? '') === 'total_asc')>見積合計の低い順</option>
        </select></label>
        <button type="submit" class="btn small-btn">絞り込む</button>
    </form>

    <table class="list apps">
        <thead><tr><th>応募者</th><th class="num">見積合計（税別）</th><th>判定</th><th>面談</th><th>受付</th><th></th></tr></thead>
        <tbody>
        @forelse ($applications as $a)
            @php($result = $a->latestResult)
            @php($cap = $result?->criteria['total_cap'] ?? null)
            <tr>
                <td class="who">
                    <a class="name" href="{{ route('admin.applications.show', $a) }}">{{ $a->name }}</a>
                    <span class="small">{{ $a->number }}・{{ $A::CATEGORY_LABELS[$a->category] }}@if ($a->company)・{{ $a->company }}@endif</span>
                </td>
                <td class="num">
                    @if ($a->latest_total !== null)
                        {{ number_format($a->latest_total) }}円
                        @if ($result?->held() && $cap)<br><span class="small over">上限を {{ number_format($a->latest_total - $cap) }}円 超過</span>@endif
                    @else <span class="small">―</span>@endif
                </td>
                <td>@if ($result)<span @class(['badge', 'ok' => $result->outcome === 'passed', 'warn' => $result->outcome === 'held', 'err' => $result->outcome === 'failed'])>{{ $R::OUTCOME_LABELS[$result->outcome] }}</span>@else<span class="badge ng">{{ $A::STATUS_LABELS[$a->status] ?? $a->status }}</span>@endif</td>
                <td>
                    @if ($a->activeBooking)
                        <span class="badge ok">予約済み</span><br><span class="small">{{ $a->activeBooking->slot->starts_at->format('n/j H:i') }}</span>
                    @elseif ($a->interview && $a->interview->stage !== 'unbooked')
                        <span class="badge ng">{{ \App\Models\Interview::STAGE_LABELS[$a->interview->stage] }}</span>
                    @elseif ($a->interview?->access_granted)
                        <span class="badge info">予約待ち</span>
                    @else <span class="small">―</span>@endif
                </td>
                <td class="small">{{ $a->created_at->format('n/j') }}</td>
                <td>@php($ms = $mailStatus[$a->id] ?? '') @if ($ms === 'failed')<span class="badge err">メール失敗</span>@elseif ($ms === 'pending')<span class="badge warn">メール未送信</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">該当する応募はありません。</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $applications->links('partials.pagination') }}
@endsection
