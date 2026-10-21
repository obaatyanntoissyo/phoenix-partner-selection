<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AppSetting;
use App\Services\ApplicationService;
use App\Support\ApplicationRules;
use App\Support\Prefectures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ApplyController extends Controller
{
    public function __construct(private ApplicationService $service) {}

    public function show(string $category): View
    {
        return view('apply.form', [
            'category' => $category,
            'prefectures' => Prefectures::ALL,
            'privacyUrl' => AppSetting::get('privacy_policy_url'),
        ]);
    }

    public function store(Request $request, string $category): RedirectResponse
    {
        // 応募区分は URL（ルート定義）で固定し、フォームの値は使わない
        if ($request->filled('website')) {
            return redirect()->route('apply.done');
        }

        $input = ApplicationRules::normalize($request->except(['category', 'website']));
        $validated = Validator::make($input, ApplicationRules::rules($category), ApplicationRules::messages(), ApplicationRules::attributes())->validate();

        $sameEmailToday = Application::query()
            ->where('email_normalized', Application::normalizeEmail($validated['email']))
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($sameEmailToday >= config('partner.max_applications_per_email_per_day')) {
            return back()->withInput()->withErrors(['email' => 'このメールアドレスからの応募が多すぎます。時間をおいてお試しいただくか、担当者へお問い合わせください。']);
        }

        $result = $this->service->register(
            $category,
            ApplicationRules::toAttributes($validated, AppSetting::get('privacy_policy_version', '未設定')),
        );
        $application = $result['application'];

        if ($category === Application::P1 && $result['token'] && AppSetting::get('flow_mode', 'direct') === 'direct') {
            // メールを待たずに見積へ進める。招待トークンは URL に出さずセッションへ交換する。
            $invitation = $application->invitations()->latest('id')->first();
            $request->session()->regenerate();
            $request->session()->put('applicant', ['application_id' => $application->id, 'invitation_id' => $invitation->id]);
            $invitation->update(['first_used_at' => now()]);

            return redirect()->route('my.conditions');
        }

        $request->session()->flash('done', [
            'category' => $category,
            'number' => $application->number,
            'duplicate' => $result['duplicate'],
        ]);

        return redirect()->route('apply.done');
    }

    public function done(Request $request): View
    {
        return view('apply.done', ['done' => $request->session()->get('done')]);
    }
}
