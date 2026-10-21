<?php

namespace App\Console\Commands;

use App\Services\MailQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('partner:send-mail {--limit=50 : 1回に処理する最大件数}')]
#[Description('送信待ち・再試行待ちのメールを送信する（CRON から毎分実行）')]
class SendMail extends Command
{
    public function handle(MailQueue $queue): int
    {
        $count = $queue->processDue((int) $this->option('limit'));
        $this->info("processed: {$count}");

        return self::SUCCESS;
    }
}
