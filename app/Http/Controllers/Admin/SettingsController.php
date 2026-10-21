<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ConfigVersion;
use App\Support\ProductionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'config' => ConfigVersion::current(),
            'versions' => ConfigVersion::query()->orderByDesc('version_no')->limit(20)->get(),
            'settings' => AppSetting::query()->pluck('value', 'key'),
        ]);
    }

    /** 見積条件・判定基準を新しい版として保存する。既存の応募に固定された版は変わらない。 */
    public function storeConfig(Request $request): RedirectResponse
    {
        $current = ConfigVersion::current();
        // 追加用の空行は保存対象にしない
        $request->merge(['estimate_items' => array_values(array_filter((array) $request->input('estimate_items', []), fn ($i) => is_array($i) && (($i['key'] ?? '') !== '' || ($i['label'] ?? '') !== '')))]);
        $data = $request->validate([
            'mockup_links' => ['required', 'array', 'min:1'],
            'mockup_links.*.label' => ['nullable', 'string', 'max:100'],
            'mockup_links.*.url' => ['nullable', 'url:https,http', 'max:500'],
            'scope_items' => ['required', 'array'],
            'scope_items.*.label' => ['nullable', 'string', 'max:100'],
            'scope_items.*.value' => ['nullable', 'string', 'max:2000'],
            'estimate_items' => ['required', 'array', 'min:1', 'max:20'],
            'estimate_items.*.key' => ['required', 'string', 'regex:/^[a-z0-9_]{1,30}$/', 'distinct'],
            'estimate_items.*.label' => ['required', 'string', 'max:50'],
            'estimate_items.*.description' => ['nullable', 'string', 'max:300'],
            'estimate_items.*.cap' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'total_cap' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'item_caps_enabled' => ['nullable', 'boolean'],
            'invite_ttl_days' => ['required', 'integer', 'min:1', 'max:90'],
            'is_test_criteria' => ['nullable', 'boolean'],
            'note' => ['required', 'string', 'max:500'],
        ], ['note.required' => '変更内容のメモを入力してください。']);

        if (app()->environment('production') && ! empty($data['is_test_criteria'])) {
            return back()->withInput()->withErrors(['is_test_criteria' => '本番環境ではテスト用の仮基準を保存できません。']);
        }

        $mockups = array_values(array_filter($data['mockup_links'], fn ($l) => ! empty($l['url'])));
        $scope = array_values(array_filter($data['scope_items'], fn ($s) => ! empty($s['label'])));
        $items = array_map(fn ($i) => [
            'key' => $i['key'],
            'label' => $i['label'],
            'description' => $i['description'] ?? '',
            'cap' => isset($i['cap']) && $i['cap'] !== '' ? (int) $i['cap'] : null,
        ], array_values($data['estimate_items']));

        $version = DB::transaction(function () use ($current, $mockups, $scope, $items, $data, $request) {
            $version = ConfigVersion::create([
                'version_no' => ConfigVersion::query()->lockForUpdate()->max('version_no') + 1,
                'mockup_links' => $mockups,
                'scope_items' => array_map(fn ($s) => ['label' => $s['label'], 'value' => ($s['value'] ?? '') !== '' ? $s['value'] : null], $scope),
                'estimate_items' => $items,
                'total_cap' => $data['total_cap'] ?? null,
                'item_caps_enabled' => (bool) ($data['item_caps_enabled'] ?? false),
                'invite_ttl_days' => $data['invite_ttl_days'],
                'is_test_criteria' => (bool) ($data['is_test_criteria'] ?? false),
                'note' => $data['note'],
                'created_by' => $request->user()->id,
            ]);
            AuditLog::record('config_version', $version, $data['note'], ['from' => $current->version_no, 'to' => $version->version_no]);

            return $version;
        });

        return back()->with('status', "募集設定を第{$version->version_no}版として保存しました。既存の応募は、招待時に固定した版のままです。");
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_url' => ['nullable', 'url:https,http', 'max:500'],
            'booking_mode' => ['required', 'in:form,reservation,slots'],
            'notify_emails' => ['nullable', 'string', 'max:1000'],
            'privacy_policy_url' => ['nullable', 'url:https,http', 'max:500'],
            'privacy_policy_version' => ['nullable', 'string', 'max:50'],
            'contact_text' => ['nullable', 'string', 'max:500'],
            'flow_mode' => ['required', 'in:direct,email'],
            'reapply_mode' => ['required', 'in:direct,hold'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        foreach (preg_split('/[\s,]+/', (string) ($data['notify_emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return back()->withInput()->withErrors(['notify_emails' => "通知先メールアドレスの形式が正しくありません：{$email}"]);
            }
        }
        if (app()->environment('production') && ! empty($data['booking_url']) && ProductionGuard::isDevBookingUrl($data['booking_url'])) {
            return back()->withInput()->withErrors(['booking_url' => '本番環境では開発用のテスト予約画面やローカルのURLを設定できません。']);
        }

        $before = AppSetting::query()->pluck('value', 'key')->all();
        DB::transaction(function () use ($data, $request, $before) {
            foreach ($data as $key => $value) {
                AppSetting::put($key, $value === null ? null : (string) $value, $request->user()->id);
            }
            $changed = array_keys(array_filter($data, fn ($v, $k) => ($before[$k] ?? null) !== ($v === null ? null : (string) $v), ARRAY_FILTER_USE_BOTH));
            AuditLog::record('settings', null, null, ['changed' => $changed]);
        });

        return back()->with('status', '設定を保存しました。');
    }
}
