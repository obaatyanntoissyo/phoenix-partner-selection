<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningResult extends Model
{
    public $timestamps = false;

    public const OUTCOME_LABELS = ['passed' => '通過', 'failed' => '不通過', 'held' => '保留'];

    public const REASON_LABELS = [
        'not_p1' => '①の応募ではない',
        'missing_agreement' => '必須の同意が不足',
        'experience_under_3y' => '実務経験が3年未満',
        'no_full_scope' => '一括対応できない',
        'no_online_meeting' => 'オンライン打ち合わせに対応できない',
        'scope_not_included' => '指定範囲をすべて含んでいない',
        'total_over_cap' => '総額が上限を超過',
        'item_over_cap' => '工程別上限を超過',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reason_codes' => 'array', 'criteria' => 'array', 'decided_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(EstimateSubmission::class, 'submission_id');
    }

    public function passed(): bool
    {
        return $this->outcome === 'passed';
    }
}
