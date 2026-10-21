<?php

use Illuminate\Support\Facades\Schedule;

// さくらのレンタルサーバ等では CRON で毎分 `php artisan schedule:run` を実行する
Schedule::command('partner:send-mail')->everyMinute()->withoutOverlapping();
