<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::query()->whereKey($key)->value('value');

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function put(string $key, ?string $value, ?int $adminId = null): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $adminId]);
    }

    public static function notifyEmails(): array
    {
        $raw = (string) static::get('notify_emails', '');

        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw))));
    }
}
