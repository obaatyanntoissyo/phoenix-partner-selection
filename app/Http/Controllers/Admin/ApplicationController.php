<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ConfigVersion;
use App\Models\EmailJob;
use App\Models\EmailTemplate;
use App\Models\Interview;
use App\Models\ScreeningResult;
use App\Services\ApplicationService;
use App\Support\ApplicationRules;
use App\Support\Csv;
use App\Support\Prefectures;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filtered($request)->with(['latestResult', 'interview', 'activeBooking.slot'])->withMax('submissions as latest_total', 'total');
        // 金額が僅差の応募を探しやすいよう、見積合計の低い順にも並べ替えられる
        $request->input('sort') === 'total_asc' ? $query->orderByRaw('latest_total is null')->orderBy('latest_total')->orderByDesc('id') : $query->latest('id');
        $applications = $query->paginate(30)->withQueryString();
        $mailStatus = EmailJob::query()
            ->whereIn('application_id', $applications->pluck('id'))
            ->get(['application_id', 'status'])
            ->groupBy('application_id')
            ->map(fn ($jobs) => $jobs->contains('status', 'dead') || $jobs->contains('status', 'failed') ? 'failed' : ($jobs->contains('status', 'pending') ? 'pending' : 'ok'));

        return view('admin.applications.index', [
            'applications' => $applications,
            'mailStatus' => $mailStatus,
            'filters' => $request->only(['category', 'status', 'q', 'mail', 'outcome', 'sort']),
            'tabCounts' => [
                'all' => Application::query()->count(),
                'held' => Application::query()->whereHas('latestResult', fn ($q) => $q->where('outcome', 'held'))->count(),
                'passed' => Application::query()->whereHas('latestResult', fn ($q) => $q->where('outcome', 'passed'))->count(),
                'failed' => Application::query()->whereHas('latestResult', fn ($q) => $q->where('outcome', 'failed'))->count(),
            ],
        ]);
    }

    public function show(Application $application): View
    {
        $application->load(['submissions.result', 'submissions.configVersion', 'results', 'interview', 'permissionEvents', 'emailJobs', 'invitations', 'configVersion', 'linked', 'activeBooking.slot']);

        return view('admin.applications.show', [
            'application' => $application,
            'audit' => AuditLog::query()->with('admin')->where('target_type', 'Application')->where('target_id', $application->id)->latest('id')->get(),
            'related' => Application::query()->where('email_normalized', $application->email_normalized)->where('id', '!=', $application->id)->get(),
            'currentConfig' => ConfigVersion::current(),
        ]);
    }

    public function create(): View
    {
        return view('admin.applications.create', ['prefectures' => Prefectures::ALL]);
    }

    /** 手動登録：入力を検証し、招待メールのプレビューを表示する。 */
    public function preview(Request $request): View|RedirectResponse
    {
        $validated = $this->validateManual($request);
        $template = EmailTemplate::currentFor('p1_invitation');
        $preview = $template->render([
            'name' => $validated['name'],
            'number' => '（登録時に採番）',
            'invite_url' => '（登録時に発行される本人用URL）',
            'expires_at' => now()->addDays(ConfigVersion::current()->invite_ttl_days)->format('Y年n月j日 H:i'),
            'contact' => AppSetting::get('contact_text', ''),
        ]);

        return view('admin.applications.preview', ['input' => $validated, 'preview' => $preview, 'templateEnabled' => $template->enabled]);
    }

    public function store(Request $request, ApplicationService $service): RedirectResponse
    {
        $validated = $this->validateManual($request);
        $send = $request->boolean('send_invite');
        $result = $service->register(
            Application::P1,
            ApplicationRules::toAttributes($validated, null) + ['privacy_agreed_at' => null],
            'admin',
            null,
            (int) $request->user()->id,
            $send,
        );
        AuditLog::record('manual_register', $result['application'], $request->input('note'), ['invite_sent' => $send && ! $result['duplicate'], 'linked' => $result['application']->linked_application_id]);

        $message = $result['duplicate']
            ? '登録しました。同じメールアドレスの応募があるため、招待は自動で送っていません。必要なら詳細画面から再招待してください。'
            : ($send ? '登録し、招待メールを送信待ちにしました。' : '登録しました（招待は未送信）。');

        return redirect()->route('admin.applications.show', $result['application'])->with('status', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $applications = $this->filtered($request)->with(['submissions', 'latestResult', 'interview'])->orderBy('id')->get();
        $itemLabels = collect(ConfigVersion::current()->estimate_items)->pluck('label', 'key');
        AuditLog::record('csv_export', null, null, ['count' => $applications->count(), 'filters' => $request->only(['category', 'status', 'q', 'mail', 'outcome'])]);

        $header = ['応募番号', '区分', '受付日時', '氏名', '会社名・屋号', 'メールアドレス', '電話番号', '都道府県', '実務経験（か月）', '月の稼働可能時間', '状態', '最新判定', '判定理由', '見積合計（税別・円）'];
        foreach ($itemLabels as $label) {
            $header[] = $label.'（税別・円）';
        }
        foreach ($itemLabels as $label) {
            $header[] = $label.'（想定工数・時間）';
        }
        array_push($header, '見積提出日時', '判定基準の版', '予約状況', '選考状況');

        return response()->streamDownload(function () use ($applications, $itemLabels, $header) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, Csv::row($header), escape: '');
            foreach ($applications as $a) {
                $submission = $a->submissions->last();
                $amounts = $submission ? collect($submission->items)->pluck('amount', 'key') : collect();
                $hours = $submission ? collect($submission->items)->pluck('hours', 'key') : collect();
                $row = [
                    $a->number, Application::CATEGORY_LABELS[$a->category], $a->created_at->format('Y-m-d H:i'),
                    $a->name, $a->company, $a->email, $a->phone, $a->prefecture, $a->experienceMonths(), $a->monthly_hours,
                    Application::STATUS_LABELS[$a->status] ?? $a->status,
                    $a->latestResult ? ScreeningResult::OUTCOME_LABELS[$a->latestResult->outcome] : '',
                    $a->latestResult ? implode(' / ', array_map(fn ($c) => ScreeningResult::REASON_LABELS[$c] ?? $c, $a->latestResult->reason_codes)) : '',
                    $submission?->total,
                ];
                foreach ($itemLabels as $key => $_) {
                    $row[] = $amounts[$key] ?? '';
                }
                foreach ($itemLabels as $key => $_) {
                    $row[] = $hours[$key] ?? '';
                }
                array_push($row,
                    $submission?->submitted_at?->format('Y-m-d H:i'),
                    $a->latestResult?->criteria['config_version_no'] ?? '',
                    $a->interview ? Interview::BOOKING_LABELS[$a->interview->booking_status] : '',
                    $a->interview ? Interview::STAGE_LABELS[$a->interview->stage] : '',
                );
                fputcsv($out, Csv::row($row), escape: '');
            }
            fclose($out);
        }, 'applications-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request): Builder
    {
        $query = Application::query();
        if (in_array($request->input('category'), ['p1', 'p2'], true)) {
            $query->where('category', $request->input('category'));
        }
        if (array_key_exists((string) $request->input('status'), Application::STATUS_LABELS)) {
            $query->where('status', $request->input('status'));
        }
        if (in_array($request->input('outcome'), ['passed', 'failed', 'held'], true)) {
            $query->whereHas('latestResult', fn ($q) => $q->where('outcome', $request->input('outcome')));
        }
        if (in_array($request->input('mail'), ['failed', 'pending'], true)) {
            $statuses = $request->input('mail') === 'failed' ? ['failed', 'dead'] : ['pending'];
            $query->whereHas('emailJobs', fn ($q) => $q->whereIn('status', $statuses));
        }
        if ($q = trim((string) $request->input('q'))) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('company', 'like', $like)->orWhere('kana', 'like', $like)->orWhere('number', 'like', $like));
        }

        return $query;
    }

    private function validateManual(Request $request): array
    {
        $input = ApplicationRules::normalize($request->except(['send_invite', 'note']));
        $rules = ApplicationRules::rules(Application::P1, false);
        // 過去の問い合わせから登録する場合、不明な項目は空欄を許す
        foreach (['phone', 'prefecture', 'role_scope', 'can_full_scope', 'can_online_meeting'] as $key) {
            $rules[$key] = array_map(fn ($r) => $r === 'required' ? 'nullable' : $r, $rules[$key]);
        }
        $rules['portfolio_url'] = ['nullable', 'string', 'max:500', 'url:http,https'];
        $rules['kana'] = ['required', 'string', 'max:50'];
        $defaults = ['phone' => '-', 'prefecture' => '未登録', 'experience_years' => 0, 'experience_months' => 0, 'monthly_hours' => 1, 'available_from' => now()->format('Y-m-d')];
        $validated = Validator::make($input, $rules, ApplicationRules::messages(), ApplicationRules::attributes())->validate();
        foreach ($defaults as $key => $value) {
            if (($validated[$key] ?? null) === null || $validated[$key] === '') {
                $validated[$key] = $value;
            }
        }

        return $validated;
    }
}
