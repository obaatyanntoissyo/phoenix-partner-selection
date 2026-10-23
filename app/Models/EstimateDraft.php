<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstimateDraft extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amounts' => 'array', 'hours' => 'array', 'extra_rates' => 'array', 'zero_reasons' => 'array', 'agreements' => 'array'];
    }
}
