@extends('layouts.admin')
@section('title', $application->number)
@section('content')
    @php($A = \App\Models\Application::class)
    @php($R = \App\Models\ScreeningResult::class)
    @php($I = \App\Models\Interview::class)
    @php($latest = $application->latestResult)
    @php($interview = $application->interview)
    @php($booking = $application->activeBooking)
    @php($lastSubmission = $application->submissions->last())
    @php($cap = $latest?->criteria['total_cap'] ?? null)
    @php($reasonText = $latest ? implode('、', array_map(fn ($c) => $R::REASON_LABELS[$c] ?? $c, $latest->reason_codes)) : '')
    @php($editable = ! $application->anonymized_at)

    <div class="page-head">
        <a class="back" href="{{ route('admin.applications.index') }}">← 応募一覧にもどる</a>
        <h1>{{ $application->name }}@if ($application->company)<span class="sub">{{ $application->company }}</span>@endif</h1>
        <div class="head-meta">
            <span class="mono">{{ $application->number }}</span>
            <span class="badge info">{{ $A::CATEGORY_LABELS[$application->category] }}</span>
            <span class="badge ng">{{ $A::STATUS_LABELS[$application->status] ?? $application->status }}</span>
            @if ($latest)
                <span @class(['badge', 'ok' => $latest->outcome === 'passed', 'warn' => $latest->outcome === 'held', 'err' => $latest->outcome === 'failed'])>判定：{{ $R::OUTCOME_LABELS[$latest->outcome] }}</span>
            @endif
            @if ($interview && $application->isP1())
                <span @class(['badge', 'ok' => $interview->booking_status === 'confirmed', 'ng' => $interview->booking_status !== 'confirmed'])>面談：{{ $I::STAGE_LABELS[$interview->stage] ?? $interview->stage }}</span>
            @endif
        </div>
        <p class="small">受付 {{ $application->created_at->format('Y年n月j日 H:i') }}（{{ ['web' => '応募フォーム', 'api' => '既存フォーム連携', 'admin' => '管理者が登録'][$application->source] ?? $application->source }}）
            ／ {{ $application->email }} ／ {{ $application->phone }}</p>
    </div>

    @if ($related->isNotEmpty())
        <p class="notice-warn">同じメールアドレスで、ほかにも応募があります：
            @foreach ($related as $r)<a href="{{ route('admin.applications.show', $r) }}">{{ $r->number }}</a>@unless ($loop->last)、@endunless @endforeach
        </p>
    @endif

    {{-- いまやること --}}
    @if ($application->isP1() && $editable)
        <section class="todo @if ($latest?->held()) todo-warn @endif">
            @if ($latest?->held())
                <h2>結果を決めて、応募者に連絡してください</h2>
                <p>見積の合計は <strong>{{ number_format($lastSubmission->total) }}円</strong>で、上限（{{ number_format($cap) }}円）を <strong>{{ number_format($lastSubmission->total - $cap) }}円</strong> 超えています。金額以外の条件はすべて満たしています。<br>
                    応募者の画面には、「選考結果は改めてメールでお送りします」と表示しています。</p>
                <form method="post" action="{{ route('admin.applications.send-result', $application) }}" class="form todo-form">
                    @csrf
                    <div class="field">
                        <label for="result-reason">メモ（社内の記録用・応募者には見えません）</label>
                        <input id="result-reason" name="reason" required maxlength="500" placeholder="例：上限を少し超えているが、実績が良いため">
                    </div>
                    <div class="actions">
                        <button type="submit" name="result" value="passed" class="btn primary">通過として連絡する</button>
                        <button type="submit" name="result" value="failed" class="btn">不通過として連絡する</button>
                    </div>
                    <p class="small">「通過」は、面談の予約案内を含む結果メールを送ります。「不通過」は、お見送りのメールを送ります。どちらも、送信後は取り消せません。</p>
                </form>
            @elseif ($booking)
                <h2>面談が決まっています</h2>
                <p class="todo-date">{{ $booking->slot->label() }}</p>
                <p>
                    @if ($booking->meet_url)<a class="btn small-btn" href="{{ $booking->meet_url }}" target="_blank" rel="noopener noreferrer">Google Meet を開く</a>@endif
                    <span @class(['badge', 'ok' => $booking->calendar_state === 'synced', 'warn' => in_array($booking->calendar_state, ['pending', 'skipped'], true), 'err' => $booking->calendar_state === 'dead'])>カレンダー：{{ \App\Models\InterviewBooking::CALENDAR_LABELS[$booking->calendar_state] ?? $booking->calendar_state }}</span>
                    <a class="small" href="{{ route('admin.slots.index') }}">面談日程の画面で予約を取り消せます</a>
                </p>
            @elseif ($latest?->passed() && $interview?->access_granted)
                <h2>応募者が面談の日時を選ぶのを待っています</h2>
                <p>応募者が空き枠を選ぶと、Googleカレンダーに予定が登録され、応募者にも招待が届きます。</p>
            @elseif ($latest?->outcome === 'failed')
                <h2>不通過です</h2>
                <p>理由：{{ $reasonText }}（応募者には理由を表示していません）<br>例外として面談に進めたい場合は、下の「その他の操作」の「面談の予約を許可する」を使ってください。</p>
            @elseif (! $lastSubmission)
                <h2>応募者が見積を入力するのを待っています</h2>
                <p>応募者に見積ページの案内が届いていない、または期限が切れている場合は、下の「その他の操作」の「見積の案内を送り直す」を使ってください。</p>
            @else
                <h2>いまは対応が必要な操作はありません</h2>
            @endif
        </section>
    @endif

    <div class="two-col">
        <div class="main-col">
            @if ($application->isP1())
            <section>
                <h2>見積と判定</h2>
                @forelse ($application->submissions as $s)
                    <h3>提出 {{ $s->sequence }} 回目（{{ $s->submitted_at->format('Y年n月j日 H:i') }}・募集設定 第{{ $s->configVersion->version_no }}版）</h3>
                    <table class="list">
                        <thead><tr><th>工程</th><th class="num">金額（税別・円）</th><th>0円の理由</th></tr></thead>
                        <tbody>
                        @foreach ($s->items as $item)
                            <tr><td>{{ $item['label'] }}</td><td class="num">{{ number_format($item['amount']) }}</td><td>{{ $item['zero_reason'] }}</td></tr>
                        @endforeach
                        </tbody>
                        <tfoot><tr><th>合計</th><td class="num total-cell">{{ number_format($s->total) }}</td><td></td></tr></tfoot>
                    </table>
                    @if ($s->result)
                        <p class="judge">
                            <span @class(['badge', 'ok' => $s->result->outcome === 'passed', 'warn' => $s->result->outcome === 'held', 'err' => $s->result->outcome === 'failed'])>{{ $R::OUTCOME_LABELS[$s->result->outcome] }}</span>
                            @if ($s->result->reason_codes)<span>{{ implode('、', array_map(fn ($c) => $R::REASON_LABELS[$c] ?? $c, $s->result->reason_codes)) }}</span>@endif
                        </p>
                        <p class="small">判定に使った基準：第{{ $s->result->criteria['config_version_no'] }}版・総額の上限 {{ number_format($s->result->criteria['total_cap']) }}円{{ $s->result->criteria['item_caps_enabled'] ? '・工程別の上限あり' : '' }}{{ !empty($s->result->criteria['is_test_criteria']) ? '・テスト用の仮基準' : '' }}</p>
                    @endif
                    <dl class="conditions">
                        <dt>対応範囲・条件</dt><dd class="pre">{{ $s->scope_conditions }}</dd>
                        <dt>見積の補足</dt><dd class="pre">{{ $s->notes ?: '―' }}</dd>
                    </dl>
                @empty
                    <p class="empty">見積はまだ提出されていません。</p>
                @endforelse
                @if ($application->submissions->isNotEmpty())
                    <p class="small">提出できる回数：{{ $application->allowed_submissions }}回（残り {{ $application->remainingSubmissions() }}回）</p>
                @endif
            </section>
            @endif

            <section>
                <h2>応募内容</h2>
                <dl class="conditions">
                    <dt>フリガナ</dt><dd>{{ $application->kana }}</dd>
                    <dt>会社名・屋号</dt><dd>{{ $application->company ?: '―' }}</dd>
                    <dt>メール</dt><dd>{{ $application->email }}</dd>
                    <dt>電話</dt><dd>{{ $application->phone }}</dd>
                    <dt>都道府県</dt><dd>{{ $application->prefecture }}</dd>
                    <dt>実務経験</dt><dd>{{ $application->experience_years }}年{{ $application->experience_months }}か月</dd>
                    <dt>月の稼働時間</dt><dd>{{ $application->monthly_hours }}時間</dd>
                    <dt>対応開始日</dt><dd>{{ $application->available_from?->format('Y年n月j日') }}</dd>
                    <dt>実績URL</dt><dd>@if ($application->portfolio_url)<a href="{{ $application->portfolio_url }}" target="_blank" rel="noopener noreferrer">{{ $application->portfolio_url }}</a>@else ―@endif {{ $application->portfolio_private ? '（非公開の実績のみ）' : '' }}</dd>
                    <dt>実績での担当範囲</dt><dd class="pre">{{ $application->role_scope ?: '―' }}</dd>
                    @if ($application->isP1())
                        <dt>一括対応</dt><dd>{{ $application->can_full_scope === null ? '未回答' : ($application->can_full_scope ? 'できる' : 'できない') }}</dd>
                        <dt>オンライン打ち合わせ</dt><dd>{{ $application->can_online_meeting === null ? '未回答' : ($application->can_online_meeting ? '対応可' : '対応不可') }}</dd>
                    @endif
                    <dt>自己紹介・補足</dt><dd class="pre">{{ $application->self_intro ?: '―' }}</dd>
                    <dt>プライバシーポリシーへの同意</dt><dd>{{ $application->privacy_agreed_at ? $application->privacy_agreed_at->format('Y年n月j日 H:i').'（版：'.($application->privacy_version ?: '未設定').'）' : '―' }}</dd>
                    <dt>適用した募集設定</dt><dd>{{ $application->configVersion ? '第'.$application->configVersion->version_no.'版' : '未固定' }}（最新は第{{ $currentConfig->version_no }}版）</dd>
                </dl>
            </section>
        </div>

        <aside class="side-col">
            @if ($application->isP1())
            <section>
                <h2>面談</h2>
                <dl class="conditions compact">
                    <dt>予約の案内</dt><dd>{{ $interview?->access_granted ? '案内できる' : '案内しない' }}</dd>
                    <dt>面談の状況</dt><dd>{{ $interview ? $I::STAGE_LABELS[$interview->stage] : '―' }}</dd>
                    <dt>面談の日時</dt><dd>{{ $booking ? $booking->slot->label() : ($interview?->interview_at?->format('Y年n月j日 H:i') ?? '―') }}</dd>
                </dl>
                @if ($application->permissionEvents->isNotEmpty())
                    <h3>案内の許可・取り消し</h3>
                    <ul class="small log">
                        @foreach ($application->permissionEvents as $e)
                            <li>{{ $e->created_at->format('n/j H:i') }}　{{ \App\Models\PermissionEvent::ACTION_LABELS[$e->action] }}　{{ $e->reason }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
            @endif

            <section>
                <h2>送ったメール</h2>
                <ul class="mail-list">
                    @forelse ($application->emailJobs as $job)
                        <li>
                            <span class="mail-name">{{ \App\Support\Defaults::templateLabels()[$job->type] ?? $job->type }}</span>
                            <span @class(['badge', 'ok' => in_array($job->status, ['sent', 'accepted'], true), 'err' => in_array($job->status, ['dead', 'failed'], true), 'warn' => $job->status === 'pending'])>{{ \App\Models\EmailJob::STATUS_LABELS[$job->status] ?? $job->status }}</span>
                            <span class="small">{{ $job->created_at->format('n/j H:i') }}</span>
                        </li>
                    @empty
                        <li class="empty">まだありません。</li>
                    @endforelse
                </ul>
                <p class="small">詳しい状況と再送は、下の「記録」にあります。</p>
            </section>
        </aside>
    </div>

    @if ($application->isP1() && $editable)
    <details class="more">
        <summary>その他の操作</summary>
        <div class="ops">
            <form method="post" action="{{ route('admin.applications.reinvite', $application) }}" class="form">
                @csrf
                <h3>見積の案内を送り直す</h3>
                <p class="small">応募者に、見積ページのURLをメールで送ります。以前のURLは使えなくなります。</p>
                <label>メモ（社内用） <input name="reason" required maxlength="500"></label>
                <label class="check"><input type="checkbox" name="apply_current_config" value="1"> 最新の募集設定（第{{ $currentConfig->version_no }}版）で判定し直す</label>
                <button type="submit" class="btn">送り直す</button>
            </form>
            @if ($application->submissions->isNotEmpty())
            <form method="post" action="{{ route('admin.applications.allow-resubmission', $application) }}" class="form">
                @csrf
                <h3>見積の出し直しを許可する</h3>
                <p class="small">応募者が見積をもう一度提出できるようにします。これまでの提出と判定は残ります。</p>
                <label>メモ（社内用） <input name="reason" required maxlength="500"></label>
                <button type="submit" class="btn">許可する</button>
            </form>
            @endif
            <form method="post" action="{{ route('admin.applications.grant-booking', $application) }}" class="form">
                @csrf
                <h3>面談の予約を許可する</h3>
                <p class="small">判定にかかわらず、応募者が面談の日時を選べるようにします。メールは自動では送られません。</p>
                <label>メモ（社内用） <input name="reason" required maxlength="500"></label>
                <button type="submit" class="btn">許可する</button>
            </form>
            <form method="post" action="{{ route('admin.applications.revoke-booking', $application) }}" class="form">
                @csrf
                <h3>面談の予約案内を止める</h3>
                <p class="small">この応募者が新しく予約できなくなります。すでに入っている予約は、面談日程の画面で取り消してください。</p>
                <label>メモ（社内用） <input name="reason" required maxlength="500"></label>
                <button type="submit" class="btn">止める</button>
            </form>
            <form method="post" action="{{ route('admin.applications.revoke-invites', $application) }}" class="form">
                @csrf
                <h3>見積ページのURLを無効にする</h3>
                <p class="small">応募者に送ったURLが使えなくなります。誤って送った場合などに使います。</p>
                <label>メモ（社内用） <input name="reason" required maxlength="500"></label>
                <button type="submit" class="btn">無効にする</button>
            </form>
            <form method="post" action="{{ route('admin.applications.interview', $application) }}" class="form">
                @csrf
                <h3>面談の状況を記録する</h3>
                <p class="small">面談のあとの進み具合を残します。</p>
                <label>状況
                    <select name="stage">
                        @foreach ($I::STAGE_LABELS as $k => $v)<option value="{{ $k }}" @selected(($interview?->stage ?? 'unbooked') === $k)>{{ $v }}</option>@endforeach
                    </select>
                </label>
                <label>面談の日時 <input type="datetime-local" name="interview_at" value="{{ $interview?->interview_at?->format('Y-m-d\TH:i') }}"></label>
                <label class="check"><input type="checkbox" name="booking_confirmed" value="1" @checked($interview?->booking_status === 'confirmed')> 予約が確定している</label>
                <button type="submit" class="btn">記録する</button>
            </form>
        </div>
    </details>
    @endif

    <details class="more">
        <summary>記録（メールの詳しい状況・操作履歴・見積ページのURL）</summary>
        <h3>メールの送信状況</h3>
        <p class="small">「送信要求受理」は、送信サーバーが受け付けたことを示します。受信箱に届いたことまでは保証しません。</p>
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
                    <td>@if (in_array($job->status, ['dead', 'failed', 'accepted'], true) && $editable)
                        <form method="post" action="{{ route('admin.applications.resend-mail', [$application, $job]) }}">@csrf<button type="submit" class="link">再送</button></form>
                    @endif</td>
                </tr>
            @empty
                <tr><td colspan="6">なし</td></tr>
            @endforelse
            </tbody>
        </table>

        <h3>操作履歴</h3>
        <ul class="small log">
            @forelse ($audit as $log)
                <li>{{ $log->created_at }}　{{ $log->admin?->name ?? 'システム' }}　{{ $log->action }}　{{ $log->reason }}</li>
            @empty
                <li>なし</li>
            @endforelse
        </ul>

        @if ($application->isP1())
        <h3>見積ページのURL</h3>
        <table class="list small">
            <thead><tr><th>発行</th><th>期限</th><th>初めて開いた日時</th><th>状態</th></tr></thead>
            <tbody>
            @forelse ($application->invitations as $inv)
                <tr><td>{{ $inv->created_at }}</td><td>{{ $inv->expires_at }}</td><td>{{ $inv->first_used_at ?? '―' }}</td><td>{{ $inv->revoked_at ? '無効（'.$inv->revoke_reason.'）' : '有効' }}</td></tr>
            @empty
                <tr><td colspan="4">なし</td></tr>
            @endforelse
            </tbody>
        </table>
        @endif
    </details>

    @if ($editable)
    <details class="more danger-zone">
        <summary>個人情報を削除する</summary>
        <form method="post" action="{{ route('admin.applications.anonymize', $application) }}" class="form">
            @csrf
            <p class="small">氏名・連絡先などの個人情報を削除します。見積の金額と判定の記録は、誰の情報か分からない形で残ります。<strong>元には戻せません。</strong></p>
            <label>理由 <input name="reason" required maxlength="500"></label>
            <label class="check"><input type="checkbox" name="confirm" value="1"> 元に戻せないことを確認しました</label>
            <button type="submit" class="btn danger">削除する</button>
        </form>
    </details>
    @endif
@endsection
