@extends('layouts.admin')
@section('title', '設定')
@section('content')
    <h1>設定</h1>

    <nav class="anchor-nav" aria-label="設定の項目">
        <a href="#criteria">判定基準（金額の上限）</a>
        <a href="#general">面談・通知・個人情報</a>
        <a href="#scope">見積条件・見積項目</a>
        <a href="#history">変更の履歴</a>
    </nav>

    <section id="criteria">
        <h2>判定基準と見積の条件　<span class="badge info">現在：第{{ $config->version_no }}版</span></h2>
        <p class="small">ここを保存すると、新しい「版」として記録されます。すでに見積ページを案内した応募者には、案内したときの版で判定します。新しい条件で判定し直したい応募者には、応募詳細の「見積の案内を送り直す」で、最新の版を使うよう選んでください。</p>
        <form method="post" action="{{ route('admin.settings.config') }}" class="form">
            @csrf
            <h3>判定基準（社内専用・応募者には表示されません）</h3>
            <div class="row">
                <label>見積総額の上限（円・税別） <input name="total_cap" type="number" min="1" value="{{ old('total_cap', $config->total_cap) }}"></label>
                <label>見積ページの有効期限（日） <input name="invite_ttl_days" type="number" min="1" max="90" value="{{ old('invite_ttl_days', $config->invite_ttl_days) }}"></label>
            </div>
            <label class="check"><input type="checkbox" name="item_caps_enabled" value="1" @checked(old('item_caps_enabled', $config->item_caps_enabled))> 工程ごとの上限も使う（下の「見積項目」で、工程ごとに上限額を入れた場合に有効）</label>
            <div class="hint-box">
                <p><strong>金額の扱い</strong></p>
                <ul>
                    <li>総額が上限と<strong>同額なら通過</strong>、上限を<strong>超えると「保留」</strong>になります。保留の応募者には、その場で「選考結果は改めてメールでお送りします」と表示し、あとで管理画面から通過・不通過を決めて連絡できます。</li>
                    <li>経験が3年未満、または一括対応ができない場合は、金額にかかわらず<strong>不通過</strong>です。</li>
                    <li>総額の上限が空欄の間は、見積を受け付けません。</li>
                </ul>
            </div>
            @unless (app()->environment('production'))
                <label class="check"><input type="checkbox" name="is_test_criteria" value="1" @checked(old('is_test_criteria', $config->is_test_criteria))> テスト用の仮基準として保存する（本番では保存できません）</label>
            @endunless

            <details class="fold" id="scope">
                <summary>見積用のモックアップ（応募者に見せるリンク）</summary>
                @foreach (array_pad($config->mockup_links, count($config->mockup_links) + 1, ['label' => '', 'url' => '']) as $i => $link)
                    <div class="row">
                        <label>表示名 <input name="mockup_links[{{ $i }}][label]" value="{{ old("mockup_links.$i.label", $link['label']) }}"></label>
                        <label class="grow">URL <input name="mockup_links[{{ $i }}][url]" type="url" value="{{ old("mockup_links.$i.url", $link['url']) }}"></label>
                    </div>
                @endforeach
                <p class="small">いちばん下の空欄の行は、リンクを追加するためのものです。</p>
            </details>

            <details class="fold">
                <summary>見積条件（応募者に見せる制作条件。空欄は「未設定」と表示されます）</summary>
                @foreach (array_pad($config->scope_items, count($config->scope_items) + 1, ['label' => '', 'value' => null]) as $i => $item)
                    <div class="row">
                        <label>項目 <input name="scope_items[{{ $i }}][label]" value="{{ old("scope_items.$i.label", $item['label']) }}"></label>
                        <label class="grow">内容 <textarea name="scope_items[{{ $i }}][value]" rows="2">{{ old("scope_items.$i.value", $item['value']) }}</textarea></label>
                    </div>
                @endforeach
            </details>

            <details class="fold">
                <summary>見積項目（応募者が金額を入力する工程）</summary>
                <table class="list">
                    <thead><tr><th>キー（英小文字）</th><th>項目名</th><th>説明</th><th>工程ごとの上限（円・税別）</th></tr></thead>
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
                <p class="small">いちばん下の行は、工程を追加するためのものです（空欄のままなら保存されません）。</p>
            </details>

            <div class="field"><label for="note">変更内容のメモ（必須）</label><input id="note" name="note" maxlength="500" value="{{ old('note') }}" required placeholder="例：総額の上限を90万円に変更"></div>
            <button type="submit" class="btn primary">新しい版として保存</button>
        </form>
    </section>

    <section id="general">
        <h2>面談・通知・個人情報</h2>
        <form method="post" action="{{ route('admin.settings.app') }}" class="form">
            @csrf
            <h3>応募の流れ</h3>
            <div class="field"><label for="flow_mode">①一括制作：応募したあと</label>
                <select id="flow_mode" name="flow_mode">
                    <option value="direct" @selected(($settings['flow_mode'] ?? 'direct') === 'direct')>そのまま見積の入力へ進む（メールは控え・再開用）</option>
                    <option value="email" @selected(($settings['flow_mode'] ?? '') === 'email')>メールのURLから見積の入力へ進む</option>
                </select></div>
            <div class="field"><label for="reapply_mode">①一括制作：同じメールアドレスで再び応募したとき</label>
                <select id="reapply_mode" name="reapply_mode">
                    <option value="hold" @selected(($settings['reapply_mode'] ?? 'hold') === 'hold')>見積へは進めず、管理者が判断する（応募詳細の「見積の案内を送り直す」で案内）</option>
                    <option value="direct" @selected(($settings['reapply_mode'] ?? '') === 'direct')>新しい応募と同じく、見積の入力へ進める</option>
                </select>
                <p class="small">どちらの場合も、同じメールアドレスからの応募は1日{{ config('partner.max_applications_per_email_per_day') }}回までです。</p></div>

            <h3>面談の予約</h3>
            <div class="field"><label for="booking_mode">予約の方法</label>
                <select id="booking_mode" name="booking_mode">
                    <option value="slots" @selected(($settings['booking_mode'] ?? '') === 'slots')>このシステムで空き枠を選ぶ（Googleカレンダーに自動で登録）</option>
                    <option value="form" @selected(($settings['booking_mode'] ?? 'form') === 'form')>希望日時を集めるフォームを使う（Googleフォームなど）</option>
                    <option value="reservation" @selected(($settings['booking_mode'] ?? '') === 'reservation')>外部の予約サービスを使う</option>
                </select>
                <p class="small">「このシステムで空き枠を選ぶ」の場合の空き枠とGoogleカレンダーの連携は、<a href="{{ route('admin.slots.index') }}">面談日程</a>の画面で設定します。</p></div>
            <details class="fold" @open(($settings['booking_mode'] ?? 'form') !== 'slots')>
                <summary>外部のフォーム・予約サービスを使う場合のURL</summary>
                <div class="field"><label for="booking_url">予約先のURL</label>
                    <input id="booking_url" name="booking_url" type="url" value="{{ old('booking_url', $settings['booking_url'] ?? '') }}">
                    <p class="small">通過した応募者にだけ、サーバーから案内します。応募番号などの個人情報はURLに付けません。</p></div>
            </details>

            <h3>通知と連絡先</h3>
            <div class="field"><label for="notify_emails">社内への通知メールの宛先（複数はカンマ区切り）</label>
                <input id="notify_emails" name="notify_emails" value="{{ old('notify_emails', $settings['notify_emails'] ?? '') }}" placeholder="例：recruit@example.co.jp">
                <p class="small">新しい応募・見積の提出・面談の予約が入ったときに知らせます。</p></div>
            <div class="field"><label for="contact_text">応募者へのメールに載せる問い合わせ先</label>
                <textarea id="contact_text" name="contact_text" rows="2">{{ old('contact_text', $settings['contact_text'] ?? '') }}</textarea></div>

            <h3>個人情報</h3>
            <div class="field"><label for="privacy_policy_url">プライバシーポリシーのURL</label>
                <input id="privacy_policy_url" name="privacy_policy_url" type="url" value="{{ old('privacy_policy_url', $settings['privacy_policy_url'] ?? '') }}"></div>
            <div class="field"><label for="privacy_policy_version">プライバシーポリシーの版（同意した記録に残します）</label>
                <input id="privacy_policy_version" name="privacy_policy_version" value="{{ old('privacy_policy_version', $settings['privacy_policy_version'] ?? '') }}" placeholder="例：2026年10月版"></div>
            <div class="field"><label for="retention_days">応募情報の保存期間（日）</label>
                <input id="retention_days" name="retention_days" type="number" min="1" value="{{ old('retention_days', $settings['retention_days'] ?? '') }}">
                <p class="small">空欄の間は、自動削除も一括削除も行いません。</p></div>

            <button type="submit" class="btn primary">保存</button>
        </form>
    </section>

    <section id="history">
        <h2>判定基準の変更履歴</h2>
        <ul class="log small">
            @foreach ($versions as $v)
                <li>第{{ $v->version_no }}版　{{ $v->created_at->format('Y-m-d H:i') }}　上限：{{ $v->total_cap ? number_format($v->total_cap).'円' : '未設定' }}{{ $v->is_test_criteria ? '（テスト用）' : '' }}　{{ $v->note }}</li>
            @endforeach
        </ul>
    </section>
@endsection
