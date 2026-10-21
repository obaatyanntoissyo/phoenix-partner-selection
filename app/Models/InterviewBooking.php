<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewBooking extends Model
{
    public const CALENDAR_LABELS = [
        'none' => '—',
        'pending' => '登録待ち',
        'synced' => '登録済み',
        'deleted' => '削除済み',
        'dead' => '失敗（要対応）',
        'skipped' => '未連携',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'calendar_next_attempt_at' => 'datetime',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(InterviewSlot::class, 'slot_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
