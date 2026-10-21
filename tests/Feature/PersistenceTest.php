<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EmailJob;
use App\Models\EstimateDraft;
use App\Models\ScreeningResult;
use App\Services\MailQueue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Feature\Concerns\PartnerHelpers;
use Tests\TestCase;

/** T22 プロセス再起動後も応募・下書き・判定・メール待ちが保持される */
class PersistenceTest extends TestCase
{
    use DatabaseMigrations, PartnerHelpers;

    public function test_t22_data_survives_process_restart(): void
    {
        $this->useTestCriteria();
        $this->configureSettings();

        $this->applyP1();
        $this->post('/my/estimate', ['amounts' => ['design' => '120000'], 'action' => 'save']);
        $this->flushSession();
        $this->applyP1(['email' => 'second@example.com']);
        $this->submitEstimate(self::TEST_CAP);
        $pending = EmailJob::query()->where('status', 'pending')->count();
        $this->assertGreaterThan(0, $pending);

        // アプリケーション（プロセス）を作り直し、DBから読み直す
        $this->refreshApplication();

        $this->assertSame(2, Application::query()->count());
        $this->assertSame(120000, EstimateDraft::query()->first()->amounts['design']);
        $this->assertSame('passed', ScreeningResult::query()->sole()->outcome);
        $this->assertSame($pending, EmailJob::query()->where('status', 'pending')->count());

        // 再起動後のワーカーが送信待ちを処理できる
        app(MailQueue::class)->processDue();
        $this->assertSame(0, EmailJob::query()->where('status', 'pending')->count());

        // 他のテストへ影響させないよう、コミット済みのデータを消す
        $this->artisan('migrate:fresh');
    }
}
