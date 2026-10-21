<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    public const P1 = 'p1';

    public const P2 = 'p2';

    public const CATEGORY_LABELS = [self::P1 => '①一括制作', self::P2 => '②8341'];

    public const STATUS_LABELS = [
        'received' => '受付',
        'invited' => '招待済み',
        'drafting' => '見積入力中',
        'submitted' => '提出済み',
        'closed' => '終了',
        'anonymized' => '削除済み',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'portfolio_private' => 'boolean',
            'can_full_scope' => 'boolean',
            'can_online_meeting' => 'boolean',
            'available_from' => 'date',
            'privacy_agreed_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    public function configVersion(): BelongsTo
    {
        return $this->belongsTo(ConfigVersion::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function draft(): HasOne
    {
        return $this->hasOne(EstimateDraft::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(EstimateSubmission::class)->orderBy('sequence');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ScreeningResult::class)->orderBy('id');
    }

    public function latestResult(): HasOne
    {
        return $this->hasOne(ScreeningResult::class)->latestOfMany();
    }

    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
    }

    public function activeBooking(): HasOne
    {
        return $this->hasOne(InterviewBooking::class, 'active_application_id');
    }

    public function permissionEvents(): HasMany
    {
        return $this->hasMany(PermissionEvent::class)->orderBy('id');
    }

    public function emailJobs(): HasMany
    {
        return $this->hasMany(EmailJob::class)->orderBy('id');
    }

    public function linked(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_application_id');
    }

    public function isP1(): bool
    {
        return $this->category === self::P1;
    }

    public function experienceMonths(): int
    {
        return $this->experience_years * 12 + $this->experience_months;
    }

    public function remainingSubmissions(): int
    {
        return max(0, $this->allowed_submissions - $this->submissions()->count());
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
