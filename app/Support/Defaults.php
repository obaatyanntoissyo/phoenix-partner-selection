<?php

namespace App\Support;

/**
 * 初期データ。予約URL・通知先などの未確定値はここに置かない（null のまま）。
 */
class Defaults
{
    public static function mockupLinks(): array
    {
        return [
            ['label' => '見積もり用の制作条件', 'url' => 'https://cerulean-pegasus-1d047d.netlify.app/estimate-scope'],
            ['label' => 'モックアップ（サイトトップ）', 'url' => 'https://cerulean-pegasus-1d047d.netlify.app/'],
        ];
    }

    /** 見積条件。value が null の項目は「未設定」と表示し、確定条件として扱わない。 */
    public static function scopeItems(): array
    {
        return [
            ['label' => '対象ページ', 'value' => 'モックアップの全公開画面（TOP、企業の方へ、お仕事を探す、求人詳細、選ばれる理由、登録の流れ、FAQ、お知らせ一覧・詳細、会社概要、応募・お問い合わせ、従業員ログイン）と、入力・確認・完了・エラー状態、検索結果0件、募集終了、404'],
            ['label' => 'ページ数', 'value' => null],
            ['label' => 'PC・スマートフォン対応', 'value' => 'PC・スマートフォンのオリジナルデザイン。トップ1案、修正2回。下層は共通部品を活用し、主要画面のスマホデザインも提出'],
            ['label' => 'CMSと必要機能', 'value' => 'WordPress（標準管理画面）。求人・お知らせの個別入力、下書き・公開・非公開・募集終了、プレビュー、画像差し替え。求人検索（AND検索・件数・並び替え・6件ずつのページ送り）'],
            ['label' => 'フォーム', 'value' => '応募・企業問い合わせの2種類。サーバー側の入力検証、管理者通知・自動返信、迷惑送信対策、二重送信対策'],
            ['label' => '支給素材', 'value' => '原稿と画像は支給。初期登録は求人12件・お知らせ6件'],
            ['label' => '原稿作成の有無', 'value' => '原稿作成は含まない（レイアウト用の文章調整・画像トリミングは含む）'],
            ['label' => '修正回数', 'value' => 'トップデザイン1案・修正2回'],
            ['label' => 'サーバー・公開作業の範囲', 'value' => 'テスト環境、本番反映1回、タイトル・説明・OGP・サイトマップ・画像最適化。サーバーや有料サービスの契約費は別記'],
            ['label' => 'マニュアルの範囲', 'value' => '更新手順書（範囲の詳細は未設定）'],
            ['label' => '打ち合わせ・保証', 'value' => '打ち合わせ3回、進行管理、検品、公開後30日間の実装不具合対応'],
            ['label' => '想定納期', 'value' => null],
        ];
    }

    /** 見積項目。項目名・説明は管理画面で変更できる。cap は工程別上限（初期は使わない）。 */
    public static function estimateItems(): array
    {
        return [
            ['key' => 'direction', 'label' => 'ディレクション', 'description' => '顧客打ち合わせ（3回）、進行管理、制作上の調整、検品', 'cap' => null],
            ['key' => 'planning', 'label' => '設計', 'description' => 'サイトマップ、ワイヤーフレーム、画面・機能の整理', 'cap' => null],
            ['key' => 'design', 'label' => 'デザイン', 'description' => '対象ページのPC・スマートフォン用デザイン（トップ1案・修正2回）', 'cap' => null],
            ['key' => 'coding', 'label' => 'コーディング', 'description' => '対象ページの実装、レスポンシブ対応、表示確認', 'cap' => null],
            ['key' => 'cms', 'label' => 'CMS構築', 'description' => 'WordPressでの求人・お知らせ管理、求人検索の実装', 'cap' => null],
            ['key' => 'form', 'label' => 'フォーム実装', 'description' => '応募・企業問い合わせフォーム、通知・自動返信、迷惑送信・二重送信対策', 'cap' => null],
            ['key' => 'content', 'label' => '初期データ登録', 'description' => '求人12件・お知らせ6件の登録、文章調整・画像トリミング', 'cap' => null],
            ['key' => 'testing', 'label' => '検証・テスト', 'description' => '対象ブラウザ・端末での表示と動作確認、キーボード操作・フォームの確認', 'cap' => null],
            ['key' => 'deploy', 'label' => 'サーバー設定・公開', 'description' => '指定環境への設置、テスト環境、本番反映、SEO基本設定、公開後確認', 'cap' => null],
            ['key' => 'manual', 'label' => '操作マニュアル作成', 'description' => '合意した範囲の更新・操作手順書の作成', 'cap' => null],
        ];
    }

    public static function settings(): array
    {
        return [
            'booking_url' => null,
            'booking_mode' => 'form', // form = 希望日時の登録 / reservation = 空き枠予約サービス / slots = このシステムで空き枠を選ぶ
            'notify_emails' => null,
            'privacy_policy_url' => null,
            'privacy_policy_version' => null,
            'contact_text' => '株式会社デザインスタジオフェニックス 採用担当',
            'flow_mode' => 'direct', // direct = 応募後そのまま見積へ / email = メールのリンクから見積へ
            'reapply_mode' => 'hold', // hold = 同じメールの再応募は管理者が判断 / direct = 再応募も見積へ
            'retention_days' => null,
        ];
    }

    public static function templates(): array
    {
        return [
            'p1_receipt' => [
                'subject' => '【フェニックス】一括制作パートナー応募受付とお見積もりのお願い',
                'enabled' => true,
                'body' => <<<'TXT'
{name} 様

このたびは一括制作パートナーにご応募いただき、ありがとうございます。
以下の内容で受け付けました。

応募番号：{number}

■ 募集概要
ディレクション、デザイン、コーディング、サーバー設定・公開、操作マニュアル作成までを
一括でご対応いただけるパートナーを募集しています（実務経験3年以上、オンライン打ち合わせ対応）。

■ お見積もりのお願い
指定のモックアップの制作費を、工程別にお見積もりください。
下記の専用URLから入力・再開できます。

{invite_url}

有効期限：{expires_at}
※このURLはご本人専用です。第三者へ渡さないでください。

■ 今後の流れ
見積のご提出後、条件の確認を行います。条件を満たした方のみ、面談のご案内に進みます。
見積のご提出や面談は、契約・発注をお約束するものではありません。

■ お問い合わせ
{contact}
TXT,
            ],
            'p1_invitation' => [
                'subject' => '【フェニックス】一括制作パートナー お見積もり入力のご案内',
                'enabled' => true,
                'body' => <<<'TXT'
{name} 様

株式会社デザインスタジオフェニックスです。
一括制作パートナーの選考について、お見積もり入力のご案内をお送りします。

応募番号：{number}

下記の専用URLから、募集条件とモックアップをご確認のうえ、工程別のお見積もりを入力してください。

{invite_url}

有効期限：{expires_at}
※このURLはご本人専用です。第三者へ渡さないでください。

見積のご提出後、条件の確認を行います。条件を満たした方のみ、面談のご案内に進みます。
見積のご提出や面談は、契約・発注をお約束するものではありません。

■ お問い合わせ
{contact}
TXT,
            ],
            'p2_receipt' => [
                'subject' => '【フェニックス】独自CMSコーディングパートナー応募受付のご案内',
                // 社内確認前のテンプレートのため、初期状態では送信しない。
                'enabled' => false,
                'body' => <<<'TXT'
{name} 様

このたびは独自CMS（8341）のコーディングパートナーにご応募いただき、ありがとうございます。

応募番号：{number}

内容を確認のうえ、担当者より面談のご案内をお送りします。今しばらくお待ちください。

■ 募集条件（基本案）
・稼働：月80時間程度を基本に、最低40時間程度
・報酬：時間単価1,500円（税込）を基本案とします
※稼働時間は目安であり、発注量を保証するものではありません。

■ 選考の流れ
1. 担当者との面談
2. 実技試験（2時間程度・無報酬）
3. 合格後、有償の教育期間（40時間）
4. 実務

選考の結果によっては、ご期待に沿えない場合があります。採用をお約束するものではありません。

■ お問い合わせ
{contact}
TXT,
            ],
            'p1_result_passed' => [
                'subject' => '【フェニックス】お見積もりのご提出ありがとうございました（面談日程のご登録）',
                'enabled' => true,
                'body' => <<<'TXT'
{name} 様

お見積もりをご提出いただき、ありがとうございました。

応募番号：{number}

ご提示いただいた内容は、今回の面談案内条件を満たしています。
次に面談日程をご登録ください。契約・発注の確定ではありません。

下記のページから面談日程の登録へお進みください（ご本人のみ利用できます）。
{booking_page_url}

日程の登録時には、応募番号をご記入ください。

■ お問い合わせ
{contact}
TXT,
            ],
            'p1_result_failed' => [
                'subject' => '【フェニックス】お見積もりのご提出ありがとうございました',
                'enabled' => true,
                'body' => <<<'TXT'
{name} 様

お見積もりをご提出いただき、ありがとうございました。

応募番号：{number}

ご回答ありがとうございました。今回の募集条件とは合致しなかったため、面談のご案内を見送らせていただきます。

■ お問い合わせ
{contact}
TXT,
            ],
            'admin_new_application' => [
                'subject' => '[応募] 新規応募 {number}（{category}）',
                'enabled' => true,
                'body' => "新しい応募がありました。\n\n応募番号：{number}\n区分：{category}\n\n詳細は管理画面で確認してください。\n{admin_url}\n",
            ],
            'admin_submission' => [
                'subject' => '[応募] 見積提出 {number}：{outcome}',
                'enabled' => true,
                'body' => "見積が正式提出され、判定しました。\n\n応募番号：{number}\n判定：{outcome}\n\n判定理由・見積内訳は管理画面で確認してください。\n{admin_url}\n",
            ],
            'admin_alert' => [
                'subject' => '[応募] 要対応：{alert}',
                'enabled' => true,
                'body' => "システムで対応が必要な事象が発生しました。\n\n内容：{alert}\n対象：{number}\n\n管理画面で確認してください。\n{admin_url}\n",
            ],
        ];
    }

    /** 面談予約（空き枠・Googleカレンダー連携）の設定。2026-10-22 追加分のため別のマイグレーションで入れる。 */
    public static function bookingSettings(): array
    {
        return [
            'booking_lead_hours' => '24', // 何時間後以降の枠から予約できるか
            'google_calendar_id' => 'primary',
            'google_meet' => '1', // 予定に Google Meet のリンクを付ける
            'google_refresh_token' => null, // 暗号化して保存
            'google_account' => null,
        ];
    }

    /** 金額が基準を超えた応募者（保留）に、その場で出すメール。結果は後から担当者が改めて連絡する。 */
    public static function resultTemplates(): array
    {
        return [
            'p1_result_held' => [
                'subject' => '【フェニックス】お見積もりのご提出ありがとうございました',
                'enabled' => true,
                'body' => <<<'TXT'
{name} 様

お見積もりをご提出いただき、ありがとうございました。

応募番号：{number}

内容を確認のうえ、選考結果は改めてメールでご連絡いたします。
今しばらくお待ちください。

お見積もりのご提出は、契約・発注をお約束するものではありません。

■ お問い合わせ
{contact}
TXT,
            ],
        ];
    }

    public static function bookingTemplates(): array
    {
        return [
            'p1_booking_confirmed' => [
                'subject' => '【フェニックス】面談日程のご予約を承りました',
                'enabled' => true,
                'body' => <<<'TXT'
{name} 様

面談日程のご予約を承りました。

応募番号：{number}
日時：{interview_at}
形式：オンライン

オンライン面談の参加URLは、別途メールでお送りします。
日程の変更をご希望の場合は、下記の問い合わせ先までご連絡ください。

面談は、契約・発注をお約束するものではありません。

■ お問い合わせ
{contact}
TXT,
            ],
            'p1_booking_cancelled' => [
                'subject' => '【フェニックス】面談日程のご予約取り消しについて',
                'enabled' => true,
                'body' => <<<'TXT'
{name} 様

お申し込みいただいた面談日程（{interview_at}）のご予約を、担当者の都合により取り消しました。
大変申し訳ございません。

担当者より改めてご連絡いたします。

応募番号：{number}

■ お問い合わせ
{contact}
TXT,
            ],
            'admin_booking' => [
                'subject' => '[応募] 面談予約 {number}：{interview_at}',
                'enabled' => true,
                'body' => "面談の予約が入りました。\n\n応募番号：{number}\n日時：{interview_at}\n\n詳細は管理画面で確認してください。\n{admin_url}\n",
            ],
        ];
    }

    public static function templateLabels(): array
    {
        return [
            'p1_receipt' => '①応募受付（自動返信）',
            'p1_invitation' => '①見積入力の招待（手動登録・再招待）',
            'p2_receipt' => '②応募受付（自動返信）',
            'p1_result_passed' => '①提出受付・通過',
            'p1_result_failed' => '①提出受付・不通過',
            'p1_result_held' => '①提出受付・結果は改めて連絡（保留）',
            'admin_new_application' => '社内：新規応募',
            'admin_submission' => '社内：見積提出と判定',
            'admin_alert' => '社内：障害・要対応',
            'p1_booking_confirmed' => '①面談予約の受付',
            'p1_booking_cancelled' => '①面談予約の取り消し',
            'admin_booking' => '社内：面談予約',
        ];
    }
}
