@extends('layouts.app', ['step' => 'estimate'])
@section('title', '見積入力')
@section('content')
    <h1>見積入力</h1>
    <p>金額はすべて<strong>税別・日本円の整数</strong>で入力してください。0円の工程には、理由（例：「デザインに含む」）が必要です。対応しないという意味での0円は入力しないでください。</p>
    <p>各工程の<strong>想定工数（時間）</strong>も入力してください。金額の根拠を確認するためのもので、整数（時間）で入力します。</p>
    <p class="hint">途中で「一時保存」すると、専用URLからいつでも再開できます。判定はこのページでは行わず、最後の「提出」のときに行います。</p>

    @include('partials.errors')

    @php($amounts = $draft?->amounts ?? [])
    @php($hours = $draft?->hours ?? [])
    @php($reasons = $draft?->zero_reasons ?? [])
    @php($agreements = $draft?->agreements ?? [])
    <form method="post" action="{{ route('my.estimate.save') }}" class="form" id="estimate-form" novalidate>
        @csrf
        <table class="estimate">
            <thead><tr><th scope="col">工程</th><th scope="col">金額（税別・円）と想定工数（時間）</th></tr></thead>
            <tbody>
            @foreach ($items as $item)
                @php($k = $item['key'])
                <tr>
                    <th scope="row">
                        <label for="amount-{{ $k }}">{{ $item['label'] }}</label>
                        <span class="hint">{{ $item['description'] }}</span>
                    </th>
                    <td id="f-amounts-{{ $k }}">
                        <input id="amount-{{ $k }}" name="amounts[{{ $k }}]" type="text" inputmode="numeric" autocomplete="off" class="amount" data-amount
                               value="{{ old('amounts.'.$k, $amounts[$k] ?? '') }}" @error('amounts.'.$k) aria-invalid="true" aria-describedby="e-amounts-{{ $k }}" @enderror>
                        @include('partials.field-error', ['name' => 'amounts.'.$k])
                        <div id="f-hours-{{ $k }}" class="hours-row">
                            <label for="hours-{{ $k }}" class="small">想定工数（時間）<span class="req">必須</span></label>
                            <span class="hours-input"><input id="hours-{{ $k }}" name="hours[{{ $k }}]" type="text" inputmode="numeric" autocomplete="off" class="amount hours"
                                   value="{{ old('hours.'.$k, $hours[$k] ?? '') }}" @error('hours.'.$k) aria-invalid="true" aria-describedby="e-hours-{{ $k }}" @enderror> 時間</span>
                            @include('partials.field-error', ['name' => 'hours.'.$k])
                        </div>
                        <div id="f-zero_reasons-{{ $k }}">
                            <label for="reason-{{ $k }}" class="small">0円の場合の理由</label>
                            <input id="reason-{{ $k }}" name="zero_reasons[{{ $k }}]" type="text" maxlength="200" value="{{ old('zero_reasons.'.$k, $reasons[$k] ?? '') }}" @error('zero_reasons.'.$k) aria-invalid="true" aria-describedby="e-zero_reasons-{{ $k }}" @enderror>
                            @include('partials.field-error', ['name' => 'zero_reasons.'.$k])
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr id="f-total"><th scope="row">合計（税別・自動計算）</th><td><output id="total" aria-live="polite">―</output> 円
                    @include('partials.field-error', ['name' => 'total'])</td></tr>
            </tfoot>
        </table>

        <div class="field" id="f-scope_conditions">
            <label for="scope_conditions">対応範囲・条件 <span class="req">必須</span></label>
            <textarea id="scope_conditions" name="scope_conditions" rows="5" maxlength="2000" @error('scope_conditions') aria-invalid="true" aria-describedby="e-scope_conditions" @enderror>{{ old('scope_conditions', $draft?->scope_conditions) }}</textarea>
            <p class="hint">提示した見積条件からの作業の除外はできません。追加の提案は「見積の補足」に分けて記入してください。</p>
            @include('partials.field-error', ['name' => 'scope_conditions'])
        </div>
        <div class="field" id="f-notes">
            <label for="notes">見積の補足 <span class="opt">任意</span></label>
            <textarea id="notes" name="notes" rows="4" maxlength="2000">{{ old('notes', $draft?->notes) }}</textarea>
            @include('partials.field-error', ['name' => 'notes'])
        </div>

        <fieldset class="field">
            <legend>確認事項 <span class="req">必須</span></legend>
            @foreach ($agreementLabels as $key => $label)
                <div id="f-agreements-{{ $key }}">
                    <label class="check"><input type="checkbox" name="agreements[{{ $key }}]" value="1" @checked(old('agreements.'.$key, ($agreements[$key] ?? false) ? '1' : null) === '1')> {{ $label }}</label>
                    @include('partials.field-error', ['name' => 'agreements.'.$key])
                </div>
            @endforeach
        </fieldset>

        <div class="actions">
            <button type="submit" name="action" value="save" class="btn">一時保存</button>
            <button type="submit" name="action" value="confirm" class="btn primary">確認画面へ</button>
        </div>
    </form>
    <p><a href="{{ route('my.conditions') }}">見積条件を見直す</a></p>
@endsection
