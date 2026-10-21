<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 開発用：予約先を「このシステムで空き枠を選ぶ」にし、2〜8日後の平日 10:00〜16:00 に1時間枠を作る。本番では実行できない。
 */
class DevSlotsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DevSlotsSeeder は本番では実行できません。');
        }

        AppSetting::put('booking_mode', 'slots');
        $rows = [];
        for ($d = 2; $d <= 8; $d++) {
            $day = today()->addDays($d);
            if ($day->isWeekend()) {
                continue;
            }
            foreach ([10, 11, 13, 14, 15] as $hour) {
                $start = $day->copy()->setTime($hour, 0);
                $rows[] = ['starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'created_at' => now(), 'updated_at' => now()];
            }
        }
        $count = DB::table('interview_slots')->insertOrIgnore($rows);
        $this->command?->info("面談の空き枠を {$count} 件作成し、予約先を「このシステムで空き枠を選ぶ」にしました。");
    }
}
