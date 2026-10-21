@extends('layouts.admin')
@section('title', 'メール文面')
@section('content')
    <h1>メール文面</h1>
    <table class="list">
        <thead><tr><th>種類</th><th>件名</th><th>版</th><th>送信</th></tr></thead>
        <tbody>
        @foreach ($templates as $type => $t)
            <tr>
                <td><a href="{{ route('admin.templates.edit', $type) }}">{{ $t['label'] }}</a></td>
                <td>{{ $t['template']?->subject }}</td>
                <td>第{{ $t['template']?->version_no }}版</td>
                <td>{{ $t['template']?->enabled ? '有効' : '無効' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
