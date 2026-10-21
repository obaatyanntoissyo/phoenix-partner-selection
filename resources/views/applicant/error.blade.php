@extends('layouts.app')
@section('title', $info['title'])
@section('content')
    <h1>{{ $info['title'] }}</h1>
    <p>{{ $info['body'] }}</p>
    @if ($kind === 'already_submitted' || $kind === 'forbidden')
        <p><a href="{{ route('my.home') }}">専用ページへ戻る</a></p>
    @endif
@endsection
