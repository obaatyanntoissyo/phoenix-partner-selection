@extends('layouts.admin')
@section('title', '手動登録')
@section('content')
    <h1>過去の問い合わせ者の手動登録（①一括制作）</h1>
    <p>過去に問い合わせのあった方を登録し、見積入力の招待を個別に送ります。次の画面で招待メールのプレビューを確認できます。</p>
    <form method="post" action="{{ route('admin.applications.preview') }}" class="form">
        @csrf
        @include('partials.application-fields', ['category' => 'p1', 'values' => [], 'admin' => true])
        <div class="field"><label for="note">登録メモ（社内用）</label><input id="note" name="note" maxlength="500" value="{{ old('note') }}"></div>
        <button type="submit" class="btn primary">招待メールをプレビュー</button>
    </form>
@endsection
