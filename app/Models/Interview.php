<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    public const BOOKING_LABELS = [
        'not_guided' => '未案内',
        'available' => '案内可能',
        'redirected' => '予約ページへ移動',
        'confirmed' => '予約確認済み',
    ];

    public const STAGE_LABELS = [
        'unbooked' => '未予約',
        'booked' => '予約確認済み',
        'interviewed' => '面談済み',
        'considering' => '契約検討',
        'contracted' => '契約',
        'declined' => '見送り',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'access_granted' => 'boolean',
            'first_redirected_at' => 'datetime',
            'interview_at' => 'datetime',
        ];
    }
}
