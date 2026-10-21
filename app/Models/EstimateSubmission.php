<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EstimateSubmission extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['items' => 'array', 'agreements' => 'array', 'submitted_at' => 'datetime', 'total' => 'integer'];
    }

    public function result(): HasOne
    {
        return $this->hasOne(ScreeningResult::class, 'submission_id');
    }

    public function configVersion(): BelongsTo
    {
        return $this->belongsTo(ConfigVersion::class);
    }
}
