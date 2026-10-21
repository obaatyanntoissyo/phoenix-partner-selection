<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** 全画面共通：検索対象外・共有キャッシュ禁止・外部スクリプト禁止など。 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        // 招待URLのトークンが外部へ Referer で漏れないようにする
        $response->headers->set('Referrer-Policy', 'no-referrer');
        // form-action は予約先へのリダイレクトにも適用される。開発環境だけ http の予約先（テスト予約画面）を許す
        $formAction = app()->environment('production') ? "'self' https:" : "'self' https: http:";
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action {$formAction}; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
