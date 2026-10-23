@extends('layouts.app', ['step' => 'confirm'])
@section('title', '見積内容の確認')
@section('content')
    <h1>見積内容の確認</h1>
    <p>内容をご確認のうえ、「見積を提出して判定する」を押してください。<strong>提出後は内容を変更できません。</strong></p>

    <h2>応募情報</h2>
    <dl class="conditions">
        <dt>応募番号</dt><dd>{{ $application->number }}</dd>
        <dt>氏名</dt><dd>{{ $application->name }}</dd>
        <dt>会社名・屋号</dt><dd>{{ $application->company ?: '―' }}</dd>
        <dt>見積条件の版</dt><dd>第{{ $application->configVersion->version_no }}版</dd>
    </dl>

    <h2>見積内訳</h2>
    <table class="estimate">
        <thead><tr><th scope="col">工程</th><th scope="col" class="num">想定工数（時間）</th><th scope="col" class="num">金額（税別・円）</th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <th scope="row">{{ $item['label'] }}</th>
                <td class="num">{{ number_format($data['hours'][$item['key']]) }}</td>
                <td class="num">{{ number_format($data['amounts'][$item['key']]) }}
                    @if ($data['amounts'][$item['key']] === 0)<br><span class="small">理由：{{ $data['zero_reasons'][$item['key']] }}</span>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><th scope="row">合計（税別）</th><td class="num">{{ number_format(array_sum($data['hours'])) }} 時間</td><td class="num total">{{ number_format($total) }} 円</td></tr></tfoot>
    </table>

    <h2>納期</h2>
    <p>{{ $data['delivery_weeks'] }} 週間（着手から公開まで）</p>

    <h2>追加の単価（税別・円）</h2>
    <dl class="conditions compact">
        @foreach ($extraRates as $rk => $rlabel)
            <dt>{{ $rlabel }}</dt><dd>{{ number_format($data['extra_rates'][$rk]) }} 円</dd>
        @endforeach
    </dl>

    <h2>対応範囲・条件</h2>
    <p class="pre">{{ $data['scope_conditions'] }}</p>
    <h2>備考（合計金額の理由・前提）</h2>
    <p class="pre">{{ $data['notes'] }}</p>
    <h2>別パターンのご提案</h2>
    <p class="pre">{{ $data['alt_proposal'] ?: '―' }}@if ($data['alt_total'] !== null)
（合計 {{ number_format($data['alt_total']) }} 円・税別）@endif</p>

    <h2>確認事項</h2>
    <ul>
        @foreach ($agreementLabels as $key => $label)
            <li>{{ $label }}：{{ $data['agreements'][$key] ? '確認済み' : '未確認' }}</li>
        @endforeach
    </ul>

    <div class="actions">
        <a class="btn" href="{{ route('my.estimate') }}">修正する</a>
        <form method="post" action="{{ route('my.submit') }}" data-once>
            @csrf
            <input type="hidden" name="submit_key" value="{{ $submitKey }}">
            <button type="submit" class="btn primary">見積を提出して判定する</button>
        </form>
    </div>
@endsection
