<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 募集設定の版。保存後は書き換えない（新しい版を追加する）。
 * total_cap / estimate_items[].cap は社内専用で、応募者向けの画面・API へ渡さない。
 */
class ConfigVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mockup_links' => 'array',
            'scope_items' => 'array',
            'estimate_items' => 'array',
            'total_cap' => 'integer',
            'item_caps_enabled' => 'boolean',
            'is_test_criteria' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderByDesc('version_no')->firstOrFail();
    }

    /** 判定に必要な基準が揃っているか。総額上限が未設定なら判定受付を止める。 */
    public function isJudgeable(): bool
    {
        if ($this->total_cap === null || $this->total_cap < 1) {
            return false;
        }
        if ($this->item_caps_enabled) {
            foreach ($this->estimate_items as $item) {
                if (! isset($item['cap']) || $item['cap'] === null) {
                    return false;
                }
            }
        }

        return true;
    }

    /** 応募者向けに出してよい項目だけ（上限を含めない）。 */
    public function publicEstimateItems(): array
    {
        return array_map(fn ($i) => ['key' => $i['key'], 'label' => $i['label'], 'description' => $i['description']], $this->estimate_items);
    }
}
