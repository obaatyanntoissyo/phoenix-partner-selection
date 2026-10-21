<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_LABELS = ['auto_grant' => '自動判定で付与', 'manual_grant' => '手動で許可', 'revoke' => '取消'];

    protected $guarded = ['id'];
}
