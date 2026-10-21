@extends('layouts.app', ['step' => 'conditions'])
@section('title', '募集条件とモックアップの確認')
@section('content')
    <h1>募集条件とモックアップの確認</h1>
    <p>{{ $application->name }} 様（応募番号：{{ $application->number }}）</p>

    <section class="box">
        <h2>募集内容</h2>
        <ul>
            <li>ディレクション、デザイン、コーディング、サーバー設定・公開、操作マニュアル作成までの一括対応が必要です。</li>
            <li>実務経験3年以上の方を対象としています。</li>
            <li>顧客とのオンライン打ち合わせへの対応が必要です。</li>
        </ul>
        <p><strong>この見積は選考のためのもので、実際の案件の発注・契約ではありません。</strong></p>
    </section>

    <section>
        <h2>見積用モックアップ</h2>
        <ul class="links">
            @foreach ($config->mockup_links as $link)
                <li><a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">{{ $link['label'] }}（別タブで開く）</a></li>
            @endforeach
        </ul>
    </section>

    <section>
        <h2>見積条件</h2>
        <dl class="conditions">
            @foreach ($config->scope_items as $item)
                <dt>{{ $item['label'] }}</dt>
                <dd>@if ($item['value'] === null)<span class="unset">未設定（確定後にお知らせします）</span>@else{{ $item['value'] }}@endif</dd>
            @endforeach
        </dl>
    </section>

    <section>
        <h2>見積の項目</h2>
        <p>次の項目ごとに、税別の金額を入力していただきます。</p>
        <ul>
            @foreach ($items as $item)
                <li><strong>{{ $item['label'] }}</strong>：{{ $item['description'] }}</li>
            @endforeach
        </ul>
    </section>

    @if ($canEstimate)
        <p><a class="btn primary" href="{{ route('my.estimate') }}">見積入力へ進む</a></p>
    @else
        <p><a class="btn" href="{{ route('my.result') }}">提出結果を見る</a></p>
    @endif
@endsection
