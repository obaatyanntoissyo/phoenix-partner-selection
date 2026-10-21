@extends('layouts.admin')
@section('title', $label)
@section('content')
    <h1>{{ $label }}（第{{ $template->version_no }}版）</h1>
    <p class="small">差し込み：{name} {number} {invite_url} {expires_at} {booking_page_url} {contact}（社内向け：{category} {outcome} {alert} {admin_url}）。内部の基準額は差し込めません。</p>
    <form method="post" action="{{ route('admin.templates.update', $type) }}" class="form">
        @csrf
        <div class="field"><label for="subject">件名</label><input id="subject" name="subject" value="{{ old('subject', $template->subject) }}" maxlength="200"></div>
        <div class="field"><label for="body">本文</label><textarea id="body" name="body" rows="20">{{ old('body', $template->body) }}</textarea></div>
        <label class="check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $template->enabled))> 送信を有効にする</label>
        <button type="submit" class="btn primary">新しい版として保存</button>
    </form>
    <h2>プレビュー（保存済みの内容・サンプル値）</h2>
    <div class="mail-preview">
        <p><strong>件名：</strong>{{ $preview['subject'] }}</p>
        <pre>{{ $preview['body'] }}</pre>
    </div>
    <h2>履歴</h2>
    <ul class="small">@foreach ($history as $h)<li>第{{ $h->version_no }}版　{{ $h->created_at }}　{{ $h->enabled ? '有効' : '無効' }}</li>@endforeach</ul>
@endsection
