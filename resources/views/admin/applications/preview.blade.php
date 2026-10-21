@extends('layouts.admin')
@section('title', '招待のプレビュー')
@section('content')
    <h1>招待メールのプレビュー</h1>
    <p>宛先：{{ $input['email'] }}（{{ $input['name'] }} 様）</p>
    @unless ($templateEnabled)<p class="warn">このテンプレートは送信無効になっています。登録しても送信されません。</p>@endunless
    <div class="mail-preview">
        <p><strong>件名：</strong>{{ $preview['subject'] }}</p>
        <pre>{{ $preview['body'] }}</pre>
    </div>
    <form method="post" action="{{ route('admin.applications.store') }}" class="form">
        @csrf
        @foreach ($input as $key => $value)
            @if (! is_array($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <input type="hidden" name="note" value="{{ request('note') }}">
        <label class="check"><input type="checkbox" name="send_invite" value="1" checked> 登録と同時に招待メールを送る</label>
        <div class="actions">
            <a class="btn" href="{{ route('admin.applications.create') }}">入力に戻る</a>
            <button type="submit" class="btn primary">登録する</button>
        </div>
    </form>
@endsection
