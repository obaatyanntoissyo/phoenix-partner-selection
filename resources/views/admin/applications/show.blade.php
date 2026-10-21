@extends('layouts.admin')
@section('title', $application->number)
@section('content')
    @php($A = \App\Models\Application::class)
    @php($R = \App\Models\ScreeningResult::class)
    @php($I = \App\Models\Interview::class)
    <h1>{{ $application->number }}　{{ $application->name }}</h1>
    <p>{{ $A::CATEGORY_LABELS[$application->category] }} ／ 状態：{{ $A::STATUS_LABELS[$application->status] ?? $application->status }} ／ 受付：{{ $application->created_at->format('Y-m-d H:i') }}（{{ ['web' => '応募フォーム', 'api' => '既存フォーム連携', 'admin' => '管理者登録'][$application->source] ?? $application->source }}）</p>
    @if ($application->linked || $related->isNotEmpty())
        <p class="warn">同じメールアドレスの記録：
            @foreach ($related as $r)<a href="{{ route('admin.applications.show', $r) }}">{{ $r->number }}</a> @endforeach
        </p>
    @endif

    <div class="grid">
    <section>
        <h2>応募内容</h2>
        <dl class="conditions">
            <dt>フリガナ</dt><dd>{{ $application->kana }}</dd>
            <dt>会社名・屋号</dt><dd>{{ $application->company ?: '―' }}</dd>
            <dt>メール</dt><dd>{{ $application->email }}</dd>
            <dt>電話</dt><dd>{{ $application->phone }}</dd>
            <dt>都道府県</dt><dd>{{ $application->prefecture }}</dd>
            <dt>実務経験</dt><dd>{{ $application->experience_years }}年{{ $application->experience_months }}か月</dd>
            <dt>月の稼働可能時間</dt><dd>{{ $application->monthly_hours }}時間</dd>
            <dt>対応開始可能日</dt><dd>{{ $application->available_from?->format('Y-m-d') }}</dd>
            <dt>実績URL</dt><dd>@if ($application->portfolio_url)<a href="{{ $application->portfolio_url }}" target="_blank" rel="noopener noreferrer">{{ $application->portfolio_url }}</a>@else ―@endif {{ $application->portfolio_private ? '（非公開実績のみ）' : '' }}</dd>
            <dt>担当範囲</dt><dd class="pre">{{ $application->role_scope ?: '―' }}</dd>
            @if ($application->isP1())
                <dt>一括対応</dt><dd>{{ $application->can_full_scope === null ? '未回答' : ($application->can_full_scope ? 'できる' : 'できない') }}</dd>
                <dt>オンライン打ち合わせ</dt><dd>{{ $application->can_online_meeting === null ? '未回答' : ($application->can_online_meeting ? '対応可' : '対応不可') }}</dd>
            @endif
            <dt>自己紹介・補足</dt><dd class="pre">{{ $application->self_intro ?: '―' }}</dd>
            <dt>プライバシー同意</dt><dd>{{ $application->privacy_agreed_at ? $application->privacy_agreed_at->format('Y-m-d H:i').'（版：'.$application->privacy_version.'）' : '―' }}</dd>
            <dt>固定した募集設定</dt><dd>{{ $application->configVersion ? '第'.$application->configVersion->version_no.'版' : '未固定' }}（最新：第{{ $currentConfig->version_no }}版）</dd>
        </dl>
    </section>

    @if ($application->isP1())
    <section>
        <h2>見積と判定</h2>
        @forelse ($application->submissions as $s)
            <h3>提出 #{{ $s->sequence }}（{{ $s->submitted_at->format('Y-m-d H:i') }}・第{{ $s->configVersion->version_no }}版）</h3>
            <table class="list">
                <thead><tr><th>工程</th><th class="num">金額（税別・円）</th><th>0円の理由</th></tr></thead>
                <tbody>
                @foreach ($s->items as $item)
                    <tr><td>{{ $item['label'] }}</td><td class="num">{{ number_format($item['amount']) }}</td><td>{{ $item['zero_reason'] }}</td></tr>
                @endforeach
                </tbody>
                <tfoot><tr><th>合計</th><td class="num">{{ number_format($s->total) }}</td><td></td></tr></tfoot>
            </table>
            <p><strong>対応範囲・条件：</strong><span class="pre">{{ $s->scope_conditions }}</span></p>
            <p><strong>見積の補足：</strong><span class="pre">{{ $s->notes ?: '―' }}</span></p>
            @if ($s->result)
                <p><strong>判定：{{ $R::OUTCOME_LABELS[$s->result->outcome] }}</strong>
                    @if ($s->result->reason_codes)（{{ implode('、', array_map(fn ($c) => $R::REASON_LABELS[$c] ?? $c, $s->result->reason_codes)) }}）@endif
                </p>
                <p class="small">利用した基準：第{{ $s->result->criteria['config_version_no'] }}版・総額上限 {{ number_format($s->result->criteria['total_cap']) }}円{{ $s->result->criteria['item_caps_enabled'] ? '・工程別上限あり' : '' }}{{ !empty($s->result->criteria['is_test_criteria']) ? '・テスト用仮基準' : '' }}</p>
            @endif
        @empty
            <p>見積はまだ提出されていません。</p>
        @endforelse
        <p class="small">提出可能回数：{{ $application->allowed_submissions }}回（残り {{ $application->remainingSubmissions() }}回）</p>
    </section>
    @endif
    </div>

    @if ($application->isP1() && ! $application->anonymized_at)
    <section>
        <h2>面談管理</h2>
        @php($iv = $application->interview)
        <p>予約案内：{{ $iv?->access_granted ? '許可' : '不可' }} ／ 予約状況：{{ $iv ? $I::BOOKING_LABELS[$iv->booking_status] : '―' }}
            @if ($iv?->first_redirected_at)（初回移動 {{ $iv->first_redirected_at->format('Y-m-d H:i') }}、計{{ $iv->redirect_count }}回）@endif</p>
        <form method="post" action="{{ route('admin.applications.interview', $application) }}" class="form row">
            @csrf
            <label>選考状況 <select name="stage">@foreach ($I::STAGE_LABELS as $k => $v)<option value="{{ $k }}" @selected($iv?->stage === $k)>{{ $v }}</option>@endforeach</select></label>
            <label>面談日時 <input type="datetime-local" name="interview_at" value="{{ $iv?->interview_at?->format('Y-m-d\TH:i') }}"></label>
            <label class="check"><input type="checkbox" name="booking_confirmed" value="1" @checked($iv?->booking_status === 'confirmed')> 予約を確認済みにする</label>
            <button type="submit" class="btn">更新</button>
        </form>
        <h3>許可・取消の履歴</h3>
        <ul class="small">
            @forelse ($application->permissionEvents as $e)
                <li>{{ $e->created_at }}　{{ \App\Models\PermissionEvent::ACTION_LABELS[$e->action] }}　{{ $e->reason }}</li>
            @empty
                <li>なし</li>
            @endforelse
        </ul>
    </section>

    <section>
        <h2>操作</h2>
        <div class="ops">
            <form method="post" action="{{ route('admin.applications.reinvite', $application) }}" class="form">
                @csrf
                <h3>招待の再発行・送信</h3>
                <p class="small">旧URLは失効します。メール本文は「メール文面」の「見積入力の招待」です。</p>
                <label>理由 <input name="reason" required maxlength="500"></label>
                <label class="check"><input type="checkbox" name="apply_current_config" value="1"> 最新の募集設定（第{{ $currentConfig->version_no }}版）を適用し直す</label>
                <button type="submit" class="btn">再発行して送信</button>
            </form>
            <form method="post" action="{{ route('admin.applications.revoke-invites', $application) }}" class="form">
                @csrf
                <h3>招待URLの失効</h3>
                <label>理由 <input name="reason" required maxlength="500"></label>
                <button type="submit" class="btn">失効させる</button>
            </form>
            @if ($application->submissions->isNotEmpty())
            <form method="post" action="{{ route('admin.applications.allow-resubmission', $application) }}" class="form">
                @csrf
                <h3>再提出の許可</h3>
                <p class="small">過去の提出と判定は残ります。</p>
                <label>理由 <input name="reason" required maxlength="500"></label>
                <button type="submit" class="btn">再提出を許可</button>
            </form>
            @endif
            <form method="post" action="{{ route('admin.applications.grant-booking', $application) }}" class="form">
                @csrf
                <h3>面談の手動許可</h3>
                <label>理由 <input name="reason" required maxlength="500"></label>
                <button type="submit" class="btn">許可する</button>
            </form>
            <form method="post" action="{{ route('admin.applications.revoke-booking', $application) }}" class="form">
                @csrf
                <h3>面談許可の取消</h3>
                <p class="small">本アプリ内の予約案内だけを止めます。外部の既存予約は取り消されません。</p>
                <label>理由 <input name="reason" required maxlength="500"></label>
                <button type="submit" class="btn">取り消す</button>
            </form>
        </div>
    </section>
    @endif

    <section>
        <h2>招待URL</h2>
        <table class="list small">
            <thead><tr><th>発行</th><th>期限</th><th>初回利用</th><th>失効</th></tr></thead>
            <tbody>
            @forelse ($application->invitations as $inv)
                <tr><td>{{ $inv->created_at }}</td><td>{{ $inv->expires_at }}</td><td>{{ $inv->first_used_at ?? '―' }}</td><td>{{ $inv->revoked_at ? $inv->revoked_at.'（'.$inv->revoke_reason.'）' : '有効' }}</td></tr>
            @empty
                <tr><td colspan="4">なし</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section>
        <h2>メール送信状況</h2>
        <p class="small">「送信要求受理」は送信サーバーが受け付けたことを示し、受信箱への配達を保証するものではありません。</p>
        <table class="list small">
            <thead><tr><th>作成</th><th>種類</th><th>状態</th><th>試行</th><th>エラー</th><th></th></tr></thead>
            <tbody>
            @forelse ($application->emailJobs as $job)
                <tr>
                    <td>{{ $job->created_at }}</td>
                    <td>{{ \App\Support\Defaults::templateLabels()[$job->type] ?? $job->type }}</td>
                    <td>{{ \App\Models\EmailJob::STATUS_LABELS[$job->status] ?? $job->status }}</td>
                    <td>{{ $job->attempts }}</td>
                    <td>{{ $job->last_error }}</td>
                    <td>@if (in_array($job->status, ['dead', 'failed', 'accepted'], true) && ! $application->anonymized_at)
                        <form method="post" action="{{ route('admin.applications.resend-mail', [$application, $job]) }}">@csrf<button type="submit" class="link">再送</button></form>
                    @endif</td>
                </tr>
            @empty
                <tr><td colspan="6">なし</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section>
        <h2>操作履歴</h2>
        <ul class="small">
            @forelse ($audit as $log)
                <li>{{ $log->created_at }}　{{ $log->admin?->name ?? 'システム' }}　{{ $log->action }}　{{ $log->reason }}</li>
            @empty
                <li>なし</li>
            @endforelse
        </ul>
    </section>

    @unless ($application->anonymized_at)
    <section>
        <h2>個人情報の削除</h2>
        <form method="post" action="{{ route('admin.applications.anonymize', $application) }}" class="form">
            @csrf
            <label>理由 <input name="reason" required maxlength="500"></label>
            <label class="check"><input type="checkbox" name="confirm" value="1"> 元に戻せないことを確認しました</label>
            <button type="submit" class="btn danger">削除する</button>
        </form>
    </section>
    @endunless
@endsection
