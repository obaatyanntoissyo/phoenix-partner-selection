<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ConfigVersion;
use App\Models\EmailJob;
use App\Services\Anonymizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $config = ConfigVersion::current();
        $missing = [];
        if ($config->total_cap === null) {
            $missing[] = '見積総額の上限（未設定の間は見積を受け付けません）';
        }
        if ($config->is_test_criteria) {
            $missing[] = '現在の判定基準は「テスト用の仮基準」です。本番では使わないでください';
        }
        foreach ($config->scope_items as $item) {
            if ($item['value'] === null || $item['value'] === '') {
                $missing[] = '見積条件「'.$item['label'].'」';
            }
        }
        foreach (['booking_url' => '面談予約先URL', 'notify_emails' => '社内通知先メール', 'privacy_policy_url' => 'プライバシーポリシーURL', 'privacy_policy_version' => 'プライバシーポリシーの版', 'retention_days' => '応募情報の保存期間'] as $key => $label) {
            if (! AppSetting::get($key)) {
                $missing[] = $label;
            }
        }

        return view('admin.dashboard', [
            'missing' => $missing,
            'counts' => [
                'p1' => Application::query()->where('category', 'p1')->count(),
                'p2' => Application::query()->where('category', 'p2')->count(),
                'submitted' => Application::query()->where('status', 'submitted')->count(),
            ],
            'mailProblems' => EmailJob::query()->whereIn('status', ['failed', 'dead'])->count(),
            'retentionDays' => AppSetting::get('retention_days'),
            'alerts' => AuditLog::query()->where('action', 'alert')->latest('id')->limit(10)->get(),
        ]);
    }

    public function purge(Request $request, Anonymizer $anonymizer): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => '確認にチェックしてください。']);
        $days = (int) AppSetting::get('retention_days');
        if ($days < 1) {
            return back()->withErrors(['confirm' => '保存期間が設定されていないため削除できません。']);
        }
        $count = $anonymizer->purgeOlderThan($days, (int) $request->user()->id);

        return back()->with('status', "保存期間を過ぎた {$count} 件の個人情報を削除しました。");
    }
}
