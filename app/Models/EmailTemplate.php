<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public static function currentFor(string $type): ?self
    {
        return static::query()->where('type', $type)->orderByDesc('version_no')->first();
    }

    /** {name} 形式の差し込み。値はプレーンテキストメールとして扱う。 */
    public function render(array $vars): array
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{'.$k.'}'] = (string) $v;
        }

        return [
            'subject' => str_replace(["\r", "\n"], ' ', strtr($this->subject, $map)),
            'body' => strtr($this->body, $map),
        ];
    }
}
