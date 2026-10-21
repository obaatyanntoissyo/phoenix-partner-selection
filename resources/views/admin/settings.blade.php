@extends('layouts.admin')
@section('title', '設定')
@section('content')
    <h1>設定</h1>

    <section>
        <h2>募集設定（見積条件・判定基準）　現在：第{{ $config->version_no }}版</h2>
        <p class="small">保存すると新しい版になります。すでに招待済みの応募は、招待時に固定した版のまま判定されます。新しい条件を適用するには、応募詳細から「最新の募集設定を適用し直す」にチェックして再招待してください。</p>
        <form method="post" action="{{ route('admin.settings.config') }}" class="form">
            @csrf
            <h3>見積用モックアップ</h3>
            @foreach (array_pad($config->mockup_links, count($config->mockup_links) + 1, ['label' => '', 'url' => '']) as $i => $link)
                <div class="row">
                    <label>表示名 <input name="mockup_links[{{ $i }}][label]" value="{{ old("mockup_links.$i.label", $link['label']) }}"></label>
                    <label>URL <input name="mockup_links[{{ $i }}][url]" type="url" value="{{ old("mockup_links.$i.url", $link['url']) }}" size="50"></label>
                </div>
            @endforeach

            <h3>見積条件（空欄は「未設定」と表示されます）</h3>
            @foreach (array_pad($config->scope_items, count($config->scope_items) + 1, ['label' => '', 'value' => null]) as $i => $item)
                <div class="row">
                    <label>項目 <input name="scope_items[{{ $i }}][label]" value="{{ old("scope_items.$i.label", $item['label']) }}"></label>
                    <label class="grow">内容 <textarea name="scope_items[{{ $i }}][value]" rows="2">{{ old("scope_items.$i.value", $item['value']) }}</textarea></label>
                </div>
            @endforeach

            <h3>見積項目</h3>
            <table class="list">
                <thead><tr><th>キー（英小文字）</th><th>項目名</th><th>説明</th><th>工程別上限（円・税別）</th></tr></thead>
                <tbody>
                @foreach (array_pad($config->estimate_items, count($config->estimate_items) + 1, ['key' => '', 'label' => '', 'description' => '', 'cap' => null]) as $i => $item)
                    @if ($item['key'] !== '' || $loop->last)
                    <tr>
                        <td><input name="estimate_items[{{ $i }}][key]" value="{{ old("estimate_items.$i.key", $item['key']) }}" size="10"></td>
                        <td><input name="estimate_items[{{ $i }}][label]" value="{{ old("estimate_items.$i.label", $item['label']) }}"></td>
                        <td><input name="estimate_items[{{ $i }}][description]" value="{{ old("estimate_items.$i.description", $item['description']) }}" size="40"></td>
                        <td><input name="estimate_items[{{ $i }}][cap]" type="number" min="1" value="{{ old("estimate_items.$i.cap", $item['cap']) }}"></td>
                    </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
            <p class="small">最後の行は追加用です（空欄のままなら保存されません）。</p>

            <h3>判定基準（社内専用・応募者には表示されません）</h3>
            <div class="row">
                <label>見積総額の上限（円・税別） <input name="total_cap" type="number" min="1" value="{{ old('total_cap', $config->total_cap) }}"></label>
                <label class="check"><input type="checkbox" name="item_caps_enabled" value="1" @checked(old('item_caps_enabled', $config->item_caps_enabled))> 工程別上限も使う</label>
                <label>招待の有効期限（日） <input name="invite_ttl_days" type="number" min="1" max="90" value="{{ old('invite_ttl_days', $config->invite_ttl_days) }}"></label>
            </div>
            <p class="small">総額上限が空欄の間は、見積を受け付けません（応募者には「現在手続きを進められません」と表示し、社内に通知します）。上限と同額は通過、上限＋1円は不通過です。</p>
            @unless (app()->environment('production'))
                <label class="check"><input type="checkbox" name="is_test_criteria" value="1" @checked(old('is_test_criteria', $config->is_test_criteria))> テスト用の仮基準として保存する（本番では保存できません）</label>
            @endunless
            <div class="field"><label for="note">変更内容のメモ（必須）</label><input id="note" name="note" maxlength="500" value="{{ old('note') }}" required></div>
            <button type="submit" class="btn primary">新しい版として保存</button>
        </form>
        <h3>版の履歴</h3>
        <ul class="small">
            @foreach ($versions as $v)
                <li>第{{ $v->version_no }}版　{{ $v->created_at }}　上限：{{ $v->total_cap ? number_format($v->total_cap).'円' : '未設定' }}{{ $v->is_test_criteria ? '（テスト用）' : '' }}　{{ $v->note }}</li>
            @endforeach
        </ul>
    </section>

    <section>
        <h2>接続先・通知・その他</h2>
        <form method="post" action="{{ route('admin.settings.app') }}" class="form">
            @csrf
            <div class="field"><label for="booking_url">面談予約先URL（Googleフォームまたは予約サービス）</label>
                <input id="booking_url" name="booking_url" type="url" value="{{ old('booking_url', $settings['booking_url'] ?? '') }}">
                <p class="small">通過者が本人確認済みのときだけ、サーバーから案内します。応募番号などの個人情報はURLに付けません。</p></div>
            <div class="field"><label for="booking_mode">予約先の種類</label>
                <select id="booking_mode" name="booking_mode">
                    <option value="form" @selected(($settings['booking_mode'] ?? 'form') === 'form')>希望日時を回収するフォーム（Googleフォーム等）</option>
                    <option value="reservation" @selected(($settings['booking_mode'] ?? '') === 'reservation')>空き枠予約サービス</option>
                </select></div>
            <div class="field"><label for="notify_emails">社内通知先メール（複数はカンマ区切り）</label>
                <input id="notify_emails" name="notify_emails" value="{{ old('notify_emails', $settings['notify_emails'] ?? '') }}"></div>
            <div class="field"><label for="contact_text">メールに記載する問い合わせ先</label>
                <textarea id="contact_text" name="contact_text" rows="2">{{ old('contact_text', $settings['contact_text'] ?? '') }}</textarea></div>
            <div class="field"><label for="privacy_policy_url">プライバシーポリシーURL</label>
                <input id="privacy_policy_url" name="privacy_policy_url" type="url" value="{{ old('privacy_policy_url', $settings['privacy_policy_url'] ?? '') }}"></div>
            <div class="field"><label for="privacy_policy_version">プライバシーポリシーの版（同意記録に保存）</label>
                <input id="privacy_policy_version" name="privacy_policy_version" value="{{ old('privacy_policy_version', $settings['privacy_policy_version'] ?? '') }}"></div>
            <div class="field"><label for="flow_mode">①応募後の進み方</label>
                <select id="flow_mode" name="flow_mode">
                    <option value="direct" @selected(($settings['flow_mode'] ?? 'direct') === 'direct')>応募後そのまま見積入力へ進む（メールは控え・再開用）</option>
                    <option value="email" @selected(($settings['flow_mode'] ?? '') === 'email')>メールの専用URLから見積入力へ進む</option>
                </select></div>
            <div class="field"><label for="retention_days">応募情報の保存期間（日）</label>
                <input id="retention_days" name="retention_days" type="number" min="1" value="{{ old('retention_days', $settings['retention_days'] ?? '') }}">
                <p class="small">空欄の間は自動削除・一括削除を行いません。</p></div>
            <button type="submit" class="btn primary">保存</button>
        </form>
    </section>
@endsection
