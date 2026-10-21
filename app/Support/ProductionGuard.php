<?php

namespace App\Support;

/**
 * 本番モードで開発用の設定（ローカル受信箱・テスト予約画面・デバッグ表示など）が
 * 使われていないかを確認する。違反があれば起動時に止める。
 */
class ProductionGuard
{
    public const DEV_MAIL_HOSTS = ['mailpit', 'mailhog', 'localhost', '127.0.0.1', '::1'];

    /** @return list<string> */
    public static function violations(): array
    {
        $violations = [];
        if (config('app.debug')) {
            $violations[] = 'APP_DEBUG が true です。';
        }
        if (config('partner.dev_tools')) {
            $violations[] = 'PARTNER_DEV_TOOLS（テスト予約画面など）が有効です。';
        }
        $mailer = config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            $violations[] = "MAIL_MAILER={$mailer} は開発用です。";
        }
        if ($mailer === 'smtp') {
            $host = strtolower((string) config('mail.mailers.smtp.host'));
            if (in_array($host, self::DEV_MAIL_HOSTS, true) || (int) config('mail.mailers.smtp.port') === 1025) {
                $violations[] = "MAIL_HOST={$host} は開発用の受信箱です。";
            }
        }
        if (strlen((string) config('app.key')) < 32) {
            $violations[] = 'APP_KEY が設定されていません。';
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $violations[] = 'APP_URL が https ではありません。';
        }

        return $violations;
    }

    /** 予約URLとして本番で使えないもの（開発用のテスト予約画面・ローカル）を判定する。 */
    public static function isDevBookingUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_contains($url, '/dev/booking')
            || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test') || str_ends_with($host, '.local');
    }
}
