{{-- 応募フォームと管理者の手動登録で共通。$category: p1 / p2、$admin: 管理画面かどうか --}}
@php($p1 = $category === 'p1')
@php($req = '<span class="req">必須</span>')
@php($opt = '<span class="opt">任意</span>')
@php($adminMode = $admin ?? false)

<div class="field" id="f-name">
    <label for="name">氏名 {!! $req !!}</label>
    <input id="name" name="name" type="text" maxlength="50" autocomplete="name" value="{{ old('name', $values['name'] ?? '') }}" @error('name') aria-invalid="true" aria-describedby="e-name" @enderror required>
    @include('partials.field-error', ['name' => 'name'])
</div>
<div class="field" id="f-kana">
    <label for="kana">フリガナ {!! $req !!}</label>
    <input id="kana" name="kana" type="text" maxlength="50" value="{{ old('kana', $values['kana'] ?? '') }}" @error('kana') aria-invalid="true" aria-describedby="e-kana" @enderror required>
    @include('partials.field-error', ['name' => 'kana'])
</div>
<div class="field" id="f-company">
    <label for="company">会社名・屋号 {!! $opt !!}</label>
    <input id="company" name="company" type="text" maxlength="100" autocomplete="organization" value="{{ old('company', $values['company'] ?? '') }}">
    <p class="hint">個人の方は空欄で構いません。</p>
    @include('partials.field-error', ['name' => 'company'])
</div>
<div class="field" id="f-email">
    <label for="email">メールアドレス {!! $req !!}</label>
    <input id="email" name="email" type="email" maxlength="254" autocomplete="email" value="{{ old('email', $values['email'] ?? '') }}" @error('email') aria-invalid="true" aria-describedby="e-email" @enderror required>
    @if ($p1 && ! $adminMode)<p class="hint">受付の控えと、見積入力を再開するための専用URLをお送りします。</p>@endif
    @include('partials.field-error', ['name' => 'email'])
</div>
<div class="field" id="f-phone">
    <label for="phone">電話番号 {!! $adminMode ? $opt : $req !!}</label>
    <input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="{{ old('phone', $values['phone'] ?? '') }}" @error('phone') aria-invalid="true" aria-describedby="e-phone" @enderror @unless($adminMode) required @endunless>
    @include('partials.field-error', ['name' => 'phone'])
</div>
<div class="field" id="f-prefecture">
    <label for="prefecture">都道府県 {!! $adminMode ? $opt : $req !!}</label>
    <select id="prefecture" name="prefecture" @error('prefecture') aria-invalid="true" @enderror @unless($adminMode) required @endunless>
        <option value="">選択してください</option>
        @foreach ($prefectures as $pref)
            <option value="{{ $pref }}" @selected(old('prefecture', $values['prefecture'] ?? '') === $pref)>{{ $pref }}</option>
        @endforeach
    </select>
    @include('partials.field-error', ['name' => 'prefecture'])
</div>
<fieldset class="field" id="f-experience_years">
    <legend>実務経験 {!! $req !!}</legend>
    <div class="inline">
        <input id="experience_years" name="experience_years" type="number" inputmode="numeric" min="0" max="60" value="{{ old('experience_years', $values['experience_years'] ?? '') }}" aria-label="実務経験（年）" required> 年
        <input id="experience_months" name="experience_months" type="number" inputmode="numeric" min="0" max="11" value="{{ old('experience_months', $values['experience_months'] ?? '0') }}" aria-label="実務経験（か月）" required> か月
    </div>
    @include('partials.field-error', ['name' => 'experience_years'])
    @include('partials.field-error', ['name' => 'experience_months'])
</fieldset>
<div class="field" id="f-portfolio_url">
    <label for="portfolio_url">実績URL {!! $p1 && ! $adminMode ? $req : $opt !!}</label>
    <input id="portfolio_url" name="portfolio_url" type="url" maxlength="500" placeholder="https://" value="{{ old('portfolio_url', $values['portfolio_url'] ?? '') }}" @error('portfolio_url') aria-invalid="true" aria-describedby="e-portfolio_url" @enderror>
    <label class="check"><input type="checkbox" name="portfolio_private" value="1" @checked(old('portfolio_private'))> 公開できる実績がない（非公開の実績のみ）</label>
    @include('partials.field-error', ['name' => 'portfolio_url'])
</div>
<div class="field" id="f-role_scope">
    <label for="role_scope">実績での担当範囲 {!! $p1 && ! $adminMode ? $req : $opt !!}</label>
    <textarea id="role_scope" name="role_scope" rows="4" maxlength="2000" @error('role_scope') aria-invalid="true" aria-describedby="e-role_scope" @enderror>{{ old('role_scope', $values['role_scope'] ?? '') }}</textarea>
    <p class="hint">ご本人・チームが担当した範囲を記入してください。非公開の実績のみの場合は、その内容と担当範囲を記述してください。</p>
    @include('partials.field-error', ['name' => 'role_scope'])
</div>
<div class="field" id="f-monthly_hours">
    <label for="monthly_hours">月あたりの稼働可能時間 {!! $req !!}</label>
    <div class="inline"><input id="monthly_hours" name="monthly_hours" type="number" inputmode="numeric" min="1" max="744" value="{{ old('monthly_hours', $values['monthly_hours'] ?? '') }}" required> 時間／月</div>
    @include('partials.field-error', ['name' => 'monthly_hours'])
</div>
<div class="field" id="f-available_from">
    <label for="available_from">対応開始可能日 {!! $req !!}</label>
    <input id="available_from" name="available_from" type="date" value="{{ old('available_from', $values['available_from'] ?? '') }}" required>
    @include('partials.field-error', ['name' => 'available_from'])
</div>
@if ($p1)
    <fieldset class="field" id="f-can_full_scope">
        <legend>一括対応 {!! $adminMode ? $opt : $req !!}</legend>
        <p class="hint">ディレクション、デザイン、コーディング、サーバー設定・公開、操作マニュアル作成までを、ご本人またはチームで一括対応できますか。</p>
        <label class="check"><input type="radio" name="can_full_scope" value="1" @checked(old('can_full_scope') === '1')> できる</label>
        <label class="check"><input type="radio" name="can_full_scope" value="0" @checked(old('can_full_scope') === '0')> できない</label>
        @include('partials.field-error', ['name' => 'can_full_scope'])
    </fieldset>
    <fieldset class="field" id="f-can_online_meeting">
        <legend>顧客とのオンライン打ち合わせ {!! $adminMode ? $opt : $req !!}</legend>
        <label class="check"><input type="radio" name="can_online_meeting" value="1" @checked(old('can_online_meeting') === '1')> 対応可</label>
        <label class="check"><input type="radio" name="can_online_meeting" value="0" @checked(old('can_online_meeting') === '0')> 対応不可</label>
        @include('partials.field-error', ['name' => 'can_online_meeting'])
    </fieldset>
@endif
<div class="field" id="f-self_intro">
    <label for="self_intro">自己紹介・補足 {!! $opt !!}</label>
    <textarea id="self_intro" name="self_intro" rows="5" maxlength="2000">{{ old('self_intro', $values['self_intro'] ?? '') }}</textarea>
    @include('partials.field-error', ['name' => 'self_intro'])
</div>
