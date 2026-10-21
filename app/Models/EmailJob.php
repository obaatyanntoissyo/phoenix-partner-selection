<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * メール送信ジョブ。本文は招待URLを含むため暗号化して保存する。
 * accepted は「送信要求受理」であり、受信箱への配達を保証しない。
 */
class EmailJob extends Model
{
    public const STATUS_LABELS = [
        'pending' => '未送信',
        'accepted' => '送信要求受理',
        'failed' => '失敗（再試行待ち）',
        'dead' => '失敗（再試行上限）',
        'skipped' => '送信しない設定',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['body'];

    protected function casts(): array
    {
        return [
            'to_address' => 'encrypted',
            'subject' => 'encrypted',
            'body' => 'encrypted',
            'next_attempt_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
