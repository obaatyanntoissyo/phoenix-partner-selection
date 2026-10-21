<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\Anonymizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('partner:purge')]
#[Description('設定した保存期間を過ぎた応募の個人情報を削除し、招待URLを失効させる（保存期間が未設定なら何もしない）')]
class PurgeExpired extends Command
{
    public function handle(Anonymizer $anonymizer): int
    {
        $days = (int) AppSetting::get('retention_days');
        if ($days < 1) {
            $this->warn('保存期間が未設定のため、削除は行いません。');

            return self::SUCCESS;
        }
        $this->info('deleted: '.$anonymizer->purgeOlderThan($days, null));

        return self::SUCCESS;
    }
}
