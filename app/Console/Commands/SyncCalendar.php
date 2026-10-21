<?php

namespace App\Console\Commands;

use App\Services\InterviewBookingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('partner:sync-calendar {--limit=20 : 1回に処理する最大件数}')]
#[Description('面談予約をGoogleカレンダーへ登録・削除する（失敗分の再試行。CRON から毎分実行）')]
class SyncCalendar extends Command
{
    public function handle(InterviewBookingService $bookings): int
    {
        $count = $bookings->processDue((int) $this->option('limit'));
        $this->info("processed: {$count}");

        return self::SUCCESS;
    }
}
