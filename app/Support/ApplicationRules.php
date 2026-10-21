<?php

namespace App\Support;

use App\Models\Application;
use Illuminate\Validation\Rule;

/** 応募フォーム・受付API・管理者の手動登録で共通の入力検証。 */
class ApplicationRules
{
    public static function normalize(array $input): array
    {
        foreach (['name', 'kana', 'company', 'email', 'phone', 'portfolio_url', 'role_scope', 'self_intro'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = trim($input[$key]);
            }
        }
        if (isset($input['phone']) && is_string($input['phone'])) {
            $input['phone'] = mb_convert_kana($input['phone'], 'a');
        }
        if (isset($input['kana']) && is_string($input['kana'])) {
            $input['kana'] = mb_convert_kana($input['kana'], 'KCV');
        }
        foreach (['experience_years', 'experience_months', 'monthly_hours'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = mb_convert_kana(trim($input[$key]), 'n');
            }
        }

        return $input;
    }

    public static function rules(string $category, bool $requirePrivacy = true): array
    {
        $p1 = $category === Application::P1;

        return [
            'name' => ['required', 'string', 'max:50'],
            'kana' => ['required', 'string', 'max:50', 'regex:/^[ァ-ヶー・\s　]+$/u'],
            'company' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'string', 'max:254', 'email:rfc'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-() ]{10,20}$/'],
            'prefecture' => ['required', Rule::in(Prefectures::ALL)],
            'experience_years' => ['required', 'integer', 'between:0,60'],
            'experience_months' => ['required', 'integer', 'between:0,11'],
            'portfolio_private' => ['nullable', 'boolean'],
            'portfolio_url' => $p1
                ? ['nullable', 'required_unless:portfolio_private,1', 'string', 'max:500', 'url:http,https']
                : ['nullable', 'string', 'max:500', 'url:http,https'],
            'role_scope' => [$p1 ? 'required' : 'nullable', 'string', 'max:2000'],
            'monthly_hours' => ['required', 'integer', 'between:1,744'],
            'available_from' => ['required', 'date_format:Y-m-d'],
            'can_full_scope' => $p1 ? ['required', 'in:0,1'] : ['prohibited'],
            'can_online_meeting' => $p1 ? ['required', 'in:0,1'] : ['prohibited'],
            'self_intro' => ['nullable', 'string', 'max:2000'],
            'privacy' => $requirePrivacy ? ['accepted'] : ['nullable'],
        ];
    }

    public static function attributes(): array
    {
        return [
            'name' => '氏名', 'kana' => 'フリガナ', 'company' => '会社名・屋号', 'email' => 'メールアドレス',
            'phone' => '電話番号', 'prefecture' => '都道府県', 'experience_years' => '実務経験（年）',
            'experience_months' => '実務経験（か月）', 'portfolio_url' => '実績URL', 'portfolio_private' => '非公開実績のみ',
            'role_scope' => '担当範囲', 'monthly_hours' => '月あたりの稼働可能時間', 'available_from' => '対応開始可能日',
            'can_full_scope' => '一括対応', 'can_online_meeting' => 'オンライン打ち合わせ', 'self_intro' => '自己紹介・補足',
            'privacy' => 'プライバシーポリシーへの同意',
        ];
    }

    public static function messages(): array
    {
        return [
            'kana.regex' => 'フリガナはカタカナで入力してください。',
            'phone.regex' => '電話番号は数字とハイフンで入力してください。',
            'portfolio_url.required_unless' => '実績URLを入力してください。非公開の実績のみの場合は、チェックを入れて担当範囲に内容を記述してください。',
            'privacy.accepted' => 'プライバシーポリシーへの同意が必要です。',
            'can_full_scope.required' => '一括対応の可否を選択してください。',
            'can_online_meeting.required' => 'オンライン打ち合わせの可否を選択してください。',
        ];
    }

    /** 検証済みの値から applications に保存する列だけを作る。 */
    public static function toAttributes(array $v, ?string $privacyVersion): array
    {
        return [
            'name' => $v['name'],
            'kana' => $v['kana'],
            'company' => $v['company'] ?? null,
            'email' => $v['email'],
            'phone' => $v['phone'],
            'prefecture' => $v['prefecture'],
            'experience_years' => (int) $v['experience_years'],
            'experience_months' => (int) $v['experience_months'],
            'portfolio_url' => $v['portfolio_url'] ?? null,
            'portfolio_private' => (bool) ($v['portfolio_private'] ?? false),
            'role_scope' => $v['role_scope'] ?? null,
            'monthly_hours' => (int) $v['monthly_hours'],
            'available_from' => $v['available_from'],
            'can_full_scope' => isset($v['can_full_scope']) ? $v['can_full_scope'] === '1' || $v['can_full_scope'] === 1 || $v['can_full_scope'] === true : null,
            'can_online_meeting' => isset($v['can_online_meeting']) ? $v['can_online_meeting'] === '1' || $v['can_online_meeting'] === 1 || $v['can_online_meeting'] === true : null,
            'self_intro' => $v['self_intro'] ?? null,
            'privacy_agreed_at' => ! empty($v['privacy']) ? now() : null,
            'privacy_version' => ! empty($v['privacy']) ? $privacyVersion : null,
        ];
    }
}
