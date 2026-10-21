<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AppSetting;
use App\Models\Interview;
use Illuminate\Support\Facades\DB;

class ApplicationService
{
    public function __construct(private InvitationService $invitations, private MailQueue $mail) {}

    /**
     * 応募を保存し、区分に応じた自動返信と社内通知を送信待ちに積む。
     *
     * 同じメール・同じ区分の応募が既にある場合も問い合わせとして保存するが、
     * 既存記録へ紐付け、①の招待は自動で発行しない（基準探りの繰り返し防止）。
     *
     * @return array{application: Application, token: ?string, duplicate: bool}
     */
    public function register(string $category, array $data, string $source = 'web', ?string $eventId = null, ?int $adminId = null, bool $sendInvite = true): array
    {
        if ($eventId !== null) {
            $existing = Application::query()->where('source', $source)->where('source_event_id', $eventId)->first();
            if ($existing) {
                return ['application' => $existing, 'token' => null, 'duplicate' => true, 'replayed' => true];
            }
        }

        return DB::transaction(function () use ($category, $data, $source, $eventId, $adminId, $sendInvite) {
            $emailNormalized = Application::normalizeEmail($data['email']);
            $previous = Application::query()
                ->where('category', $category)
                ->where('email_normalized', $emailNormalized)
                ->whereNull('anonymized_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            $application = Application::create($data + [
                'number' => $this->nextNumber($category),
                'category' => $category,
                'status' => 'received',
                'source' => $source,
                'source_event_id' => $eventId,
                'email_normalized' => $emailNormalized,
                'linked_application_id' => $previous?->id,
            ]);

            $token = null;
            if ($category === Application::P1) {
                Interview::create(['application_id' => $application->id]);
                if (! $previous && $sendInvite) {
                    $token = $this->invitations->issue($application, $adminId);
                    $application->refresh();
                    $this->mail->enqueue(
                        $source === 'admin' ? 'p1_invitation' : 'p1_receipt',
                        $application,
                        [$application->email],
                        $this->inviteVars($application, $token),
                        'receipt:'.$application->id,
                        $adminId,
                    );
                }
            } else {
                $this->mail->enqueue('p2_receipt', $application, [$application->email], [
                    'name' => $application->name,
                    'number' => $application->number,
                ], 'receipt:'.$application->id);
            }

            if ($source !== 'admin') {
                $this->mail->enqueue('admin_new_application', $application, AppSetting::notifyEmails(), [
                    'number' => $application->number,
                    'category' => Application::CATEGORY_LABELS[$category],
                    'admin_url' => route('admin.applications.show', $application),
                ], 'admin-new:'.$application->id);
            }

            return ['application' => $application, 'token' => $token, 'duplicate' => (bool) $previous, 'replayed' => false];
        });
    }

    public function inviteVars(Application $application, string $token): array
    {
        $invitation = $this->invitations->activeInvitation($application);

        return [
            'name' => $application->name,
            'number' => $application->number,
            'invite_url' => $this->invitations->inviteUrl($token),
            'expires_at' => $invitation?->expires_at->timezone(config('app.timezone'))->format('Y年n月j日 H:i'),
        ];
    }

    private function nextNumber(string $category): string
    {
        $year = (int) now()->format('Y');
        DB::table('application_counters')->insertOrIgnore(['category' => $category, 'year' => $year, 'last_no' => 0]);
        $row = DB::table('application_counters')->where(['category' => $category, 'year' => $year])->lockForUpdate()->first();
        $next = $row->last_no + 1;
        DB::table('application_counters')->where(['category' => $category, 'year' => $year])->update(['last_no' => $next]);

        return sprintf('%s-%d-%05d', strtoupper($category), $year, $next);
    }
}
