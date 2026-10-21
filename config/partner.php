<?php

return [
    // 開発用のテスト予約画面などを有効にする。本番（APP_ENV=production）では有効にできない。
    'dev_tools' => (bool) env('PARTNER_DEV_TOOLS', false),

    // 既存フォームからのサーバー間受付API用のキー。ブラウザへ出さないこと。
    'intake_api_key' => env('PARTNER_INTAKE_API_KEY'),

    // 1つのメールアドレスから1日に受け付ける応募数の上限（送信乱用の防止）
    'max_applications_per_email_per_day' => (int) env('PARTNER_MAX_APPLICATIONS_PER_EMAIL_PER_DAY', 3),

    // Googleカレンダー連携（面談予約）。Google Cloud で作成した OAuth クライアント（ウェブアプリケーション）の値。
    // リダイレクトURIには {APP_URL}/admin/google/callback を登録する。
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],
];
