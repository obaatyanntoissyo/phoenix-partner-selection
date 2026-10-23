<?php

namespace App\Http\Controllers\Applicant;

use App\Exceptions\ApplicantFlowException;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Invitation;
use App\Services\EstimateService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PDOException;

class MyController extends Controller
{
    public function __construct(private EstimateService $estimates) {}

    public function home(Request $request): RedirectResponse
    {
        $application = $this->application($request);

        return $application->latestResult && $application->remainingSubmissions() < 1
            ? redirect()->route('my.result')
            : redirect()->route('my.conditions');
    }

    public function conditions(Request $request): View
    {
        $application = $this->application($request);

        return view('my.conditions', [
            'application' => $application,
            'config' => $application->configVersion,
            'items' => $application->configVersion->publicEstimateItems(),
            'canEstimate' => $application->remainingSubmissions() > 0,
        ]);
    }

    public function estimate(Request $request): View|RedirectResponse
    {
        $application = $this->application($request);
        if ($redirect = $this->guardEstimate($request, $application)) {
            return $redirect;
        }
        $draft = $application->draft;

        return view('my.estimate', [
            'application' => $application,
            'items' => $application->configVersion->publicEstimateItems(),
            'draft' => $draft,
            'extraRates' => EstimateService::EXTRA_RATES,
            'agreementLabels' => EstimateService::AGREEMENTS,
        ]);
    }

    public function saveEstimate(Request $request): RedirectResponse
    {
        $application = $this->application($request);
        if ($redirect = $this->guardEstimate($request, $application)) {
            return $redirect;
        }
        $toConfirm = $request->input('action') === 'confirm';
        $checked = $this->estimates->validate($application->configVersion, $request->only([...EstimateService::FIELDS, 'agreements']), $toConfirm);

        // 形式の誤りがある値は保存しない。入力値は画面に残して修正してもらう。
        $formatErrors = $toConfirm ? $this->estimates->validate($application->configVersion, $request->all(), false)['errors'] : $checked['errors'];
        if ($formatErrors->isEmpty()) {
            $this->saveDraftOrFail($application, $checked['data']);
        }
        if ($checked['errors']->isNotEmpty()) {
            return redirect()->route('my.estimate')->withInput()->withErrors($checked['errors']);
        }

        return $toConfirm
            ? redirect()->route('my.confirm')
            : redirect()->route('my.estimate')->with('status', '入力内容を一時保存しました。このページを閉じても、専用URLから再開できます。');
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $application = $this->application($request);
        if ($redirect = $this->guardEstimate($request, $application)) {
            return $redirect;
        }
        $draft = $application->draft;
        $checked = $this->estimates->validate($application->configVersion, $draft ? $draft->only(EstimateService::FIELDS) + [
            'agreements' => array_map(fn ($v) => $v ? '1' : '0', $draft->agreements ?? []),
        ] : [], true);
        if ($checked['errors']->isNotEmpty()) {
            return redirect()->route('my.estimate')->withErrors($checked['errors']);
        }

        $key = $request->session()->get('applicant.submit_key') ?: Str::random(40);
        $request->session()->put('applicant.submit_key', $key);

        return view('my.confirm', [
            'application' => $application,
            'items' => $application->configVersion->publicEstimateItems(),
            'data' => $checked['data'],
            'total' => $checked['total'],
            'extraRates' => EstimateService::EXTRA_RATES,
            'agreementLabels' => EstimateService::AGREEMENTS,
            'submitKey' => $key,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $application = $this->application($request);
        $key = (string) $request->input('submit_key');
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $key)) {
            return redirect()->route('my.confirm');
        }

        try {
            $outcome = $this->estimates->submit($application, $key);
        } catch (ValidationException $e) {
            return redirect()->route('my.estimate')->withErrors($e->errors());
        } catch (ApplicantFlowException $e) {
            if ($e->kind === 'already_submitted') {
                return redirect()->route('my.result');
            }
            throw $e;
        } catch (QueryException|PDOException $e) {
            Log::error('estimate submit failed', ['application_id' => $application->id, 'error' => class_basename($e)]);
            throw new ApplicantFlowException('save_failed');
        }

        if ($outcome['token']) {
            // 通過時に招待を発行し直したので、このセッションを新しい招待へ付け替える
            $invitation = Invitation::query()->where('token_hash', Invitation::hashToken($outcome['token']))->first();
            $request->session()->put('applicant.invitation_id', $invitation->id);
        }
        $request->session()->forget('applicant.submit_key');

        return redirect()->route('my.result');
    }

    public function result(Request $request): View|RedirectResponse
    {
        $application = $this->application($request);
        $result = $application->latestResult;
        if (! $result) {
            return redirect()->route('my.conditions');
        }

        return view('my.result', [
            'application' => $application,
            'passed' => $result->passed(),
            'held' => $result->held(),
            'bookingAvailable' => (bool) $application->interview?->access_granted,
            'canResubmit' => $application->remainingSubmissions() > 0,
        ]);
    }

    private function application(Request $request): Application
    {
        return $request->attributes->get('application');
    }

    /** 見積の入力・確認ができる状態か。提出済み・期限切れ・判定設定不足を止める。 */
    private function guardEstimate(Request $request, Application $application): ?RedirectResponse
    {
        if ($application->remainingSubmissions() < 1) {
            return redirect()->route('my.result');
        }
        /** @var Invitation $invitation */
        $invitation = $request->attributes->get('invitation');
        if ($invitation->isExpired()) {
            throw new ApplicantFlowException('expired');
        }
        $this->estimates->requireJudgeableConfig($application);

        return null;
    }

    private function saveDraftOrFail(Application $application, array $data): void
    {
        try {
            $this->estimates->saveDraft($application, $data);
        } catch (QueryException|PDOException $e) {
            Log::error('draft save failed', ['application_id' => $application->id, 'error' => class_basename($e)]);
            throw new ApplicantFlowException('save_failed');
        }
    }
}
