@extends('layouts.app')
@section('title', $category === 'p1' ? '一括制作パートナー応募' : '独自CMSコーディングパートナー応募')
@section('content')
    @if ($category === 'p1')
        <h1>一括制作パートナー 応募フォーム</h1>
        <div class="box">
            <p>ディレクション、デザイン、コーディング、サーバー設定・公開、操作マニュアル作成までを、ご本人またはチームで一括対応できる方を募集しています。</p>
            <ul>
                <li>実務経験3年以上</li>
                <li>顧客とのオンライン打ち合わせに対応できること</li>
                <li>デザインのみ・コーディングのみの部分的な外注は募集していません</li>
            </ul>
            <p>応募後、指定のモックアップの制作費を工程別にお見積もりいただきます。見積のご提出は、契約・発注をお約束するものではありません。</p>
        </div>
    @else
        <h1>独自CMS（8341）コーディングパートナー 応募フォーム</h1>
        <div class="box">
            <p>弊社の独自CMSのコーディングを継続してご担当いただける方を募集しています。応募後、担当者から面談のご案内をお送りします。</p>
        </div>
    @endif

    @include('partials.errors')

    <form method="post" action="{{ $category === 'p1' ? route('apply.p1.store') : route('apply.p2.store') }}" class="form" data-once novalidate>
        @csrf
        <div class="hp" aria-hidden="true"><label>空欄のままにしてください<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        @include('partials.application-fields', ['category' => $category, 'values' => []])
        <div class="field" id="f-privacy">
            <label class="check"><input type="checkbox" name="privacy" value="1" @checked(old('privacy')) required>
                @if ($privacyUrl)
                    <a href="{{ $privacyUrl }}" target="_blank" rel="noopener">プライバシーポリシー</a>に同意する
                @else
                    プライバシーポリシー（準備中）に同意する
                @endif
                <span class="req">必須</span></label>
            @include('partials.field-error', ['name' => 'privacy'])
        </div>
        <button type="submit" class="btn primary">{{ $category === 'p1' ? '応募して見積へ進む' : '応募する' }}</button>
    </form>
@endsection
