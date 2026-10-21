<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Support\Defaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public const SAMPLE_VARS = [
        'name' => '山田 太郎',
        'number' => 'P1-2026-00001',
        'invite_url' => 'https://example.invalid/invite/（本人用URL）',
        'expires_at' => '2026年11月4日 10:00',
        'booking_page_url' => 'https://example.invalid/invite/（本人用URL）',
        'category' => '①一括制作',
        'outcome' => '通過',
        'alert' => 'メール送信失敗',
        'admin_url' => 'https://example.invalid/admin/applications/1',
    ];

    public function index(): View
    {
        $templates = collect(Defaults::templateLabels())->map(fn ($label, $type) => ['label' => $label, 'template' => EmailTemplate::currentFor($type)]);

        return view('admin.templates.index', ['templates' => $templates]);
    }

    public function edit(string $type): View
    {
        abort_unless(array_key_exists($type, Defaults::templateLabels()), 404);
        $template = EmailTemplate::currentFor($type);

        return view('admin.templates.edit', [
            'type' => $type,
            'label' => Defaults::templateLabels()[$type],
            'template' => $template,
            'preview' => $template->render(self::SAMPLE_VARS + ['contact' => (string) AppSetting::get('contact_text', '')]),
            'history' => EmailTemplate::query()->where('type', $type)->orderByDesc('version_no')->limit(10)->get(),
        ]);
    }

    /** 編集内容は新しい版として保存する。送信済みのメールは保存時の版を記録している。 */
    public function update(Request $request, string $type): RedirectResponse
    {
        abort_unless(array_key_exists($type, Defaults::templateLabels()), 404);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'enabled' => ['nullable', 'boolean'],
        ]);
        if (in_array($type, ['p1_receipt', 'p1_invitation'], true) && ! str_contains($data['body'], '{invite_url}')) {
            return back()->withInput()->withErrors(['body' => '本文に {invite_url} を含めてください。']);
        }
        if ($type === 'p2_receipt' && preg_match('/\{(invite_url|booking_page_url)\}|netlify|estimate/i', $data['body'])) {
            return back()->withInput()->withErrors(['body' => '②の自動返信には、①のモックアップ・見積URLを含められません。']);
        }

        $template = EmailTemplate::create([
            'type' => $type,
            'version_no' => (int) EmailTemplate::query()->where('type', $type)->max('version_no') + 1,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'enabled' => (bool) ($data['enabled'] ?? false),
            'created_by' => $request->user()->id,
        ]);
        AuditLog::record('template', $template, null, ['type' => $type, 'version' => $template->version_no, 'enabled' => $template->enabled]);

        return redirect()->route('admin.templates.edit', $type)->with('status', "第{$template->version_no}版として保存しました。");
    }
}
