<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InterviewSlot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function activeBooking(): HasOne
    {
        return $this->hasOne(InterviewBooking::class, 'active_slot_id');
    }

    /** 応募者・メール向けの表示（例：2026年11月5日(木) 10:00〜11:00） */
    public function label(): string
    {
        $week = ['日', '月', '火', '水', '木', '金', '土'][$this->starts_at->dayOfWeek];

        return $this->starts_at->format('Y年n月j日')."({$week}) ".$this->starts_at->format('H:i').'〜'.$this->ends_at->format('H:i');
    }
}
