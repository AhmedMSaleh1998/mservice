<?php

namespace Modules\Core\Services;

use Modules\Core\Models\AuditLog;

class AuditLogger
{
    public static function log($action, $old = null, $new = null)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}