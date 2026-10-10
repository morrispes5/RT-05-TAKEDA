<?php

namespace App\Models;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'safe_diff' => 'array',
        ];
    }
}
