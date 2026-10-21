<?php

namespace App\Providers;

use App\Support\ProductionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            $violations = ProductionGuard::violations();
            if ($violations) {
                throw new RuntimeException('本番モードで使えない設定があります: '.implode(' ', $violations));
            }
            URL::forceScheme('https');
        }

        RateLimiter::for('apply', fn (Request $request) => [
            Limit::perMinute(5)->by('apply-min:'.$request->ip()),
            Limit::perDay(30)->by('apply-day:'.$request->ip()),
        ]);
        RateLimiter::for('invite', fn (Request $request) => Limit::perMinute(10)->by('invite:'.$request->ip()));
        RateLimiter::for('applicant', fn (Request $request) => Limit::perMinute(60)->by('applicant:'.$request->session()->getId()));
        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute(5)->by('admin-login:'.$request->ip()),
            Limit::perHour(20)->by('admin-login-email:'.mb_strtolower((string) $request->input('email'))),
        ]);
        RateLimiter::for('intake', fn (Request $request) => Limit::perMinute(30)->by('intake:'.$request->ip()));
    }
}
