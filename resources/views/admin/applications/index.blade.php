@extends('layouts.admin')
@section('title', '応募一覧')
@section('content')
    <h1>応募一覧</h1>
    <form method="get" class="filters">
        <label>区分 <select name="category"><option value="">すべて</option>
            @foreach (\App\Models\Application::CATEGORY_LABELS as $k => $v)<option value="{{ $k }}" @selected(($filters['category'] ?? '') === $k)>{{ $v }}</option>@endforeach
        </select></label>
        <label>状態 <select name="status"><option value="">すべて</option>
            @foreach (\App\Models\Application::STATUS_LABELS as $k => $v)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $v }}</option>@endforeach
        </select></label>
        <label>判定 <select name="outcome"><option value="">すべて</option>
            @foreach (\App\Models\ScreeningResult::OUTCOME_LABELS as $k => $v)<option value="{{ $k }}" @selected(($filters['outcome'] ?? '') === $k)>{{ $v }}</option>@endforeach
        </select></label>
        <label>メール <select name="mail"><option value="">すべて</option>
            <option value="failed" @selected(($filters['mail'] ?? '') === 'failed')>失敗あり</option>
            <option value="pending" @selected(($filters['mail'] ?? '') === 'pending')>未送信あり</option>
        </select></label>
        <label>氏名・会社・番号 <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"></label>
        <button type="submit" class="btn">絞り込む</button>
        <a class="btn" href="{{ route('admin.applications.export', $filters) }}">CSV出力</a>
    </form>
    <table class="list">
        <thead><tr><th>応募番号</th><th>区分</th><th>氏名・会社</th><th>受付日</th><th>状態</th><th>判定</th><th>予約</th><th>メール</th></tr></thead>
        <tbody>
        @forelse ($applications as $a)
            <tr>
                <td><a href="{{ route('admin.applications.show', $a) }}">{{ $a->number }}</a></td>
                <td>{{ \App\Models\Application::CATEGORY_LABELS[$a->category] }}</td>
                <td>{{ $a->name }}@if ($a->company)<br><span class="small">{{ $a->company }}</span>@endif</td>
                <td>{{ $a->created_at->format('Y-m-d') }}</td>
                <td><span class="badge">{{ \App\Models\Application::STATUS_LABELS[$a->status] ?? $a->status }}</span></td>
                <td>@if ($a->latestResult)<span @class(['badge', 'ok' => $a->latestResult->outcome === 'passed', 'ng' => $a->latestResult->outcome !== 'passed'])>{{ \App\Models\ScreeningResult::OUTCOME_LABELS[$a->latestResult->outcome] }}</span>@else ―@endif</td>
                <td>@if ($a->interview)<span @class(['badge', 'ok' => $a->interview->booking_status === 'confirmed', 'info' => in_array($a->interview->booking_status, ['available', 'redirected'], true)])>{{ \App\Models\Interview::BOOKING_LABELS[$a->interview->booking_status] }}</span>@else ―@endif</td>
                <td>@php($ms = $mailStatus[$a->id] ?? '') @if ($ms)<span @class(['badge', 'err' => $ms === 'failed', 'warn' => $ms === 'pending'])>{{ ['failed' => '失敗あり', 'pending' => '未送信あり', 'ok' => '済'][$ms] }}</span>@else ―@endif</td>
            </tr>
        @empty
            <tr><td colspan="8">該当する応募はありません。</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $applications->links('partials.pagination') }}
@endsection
