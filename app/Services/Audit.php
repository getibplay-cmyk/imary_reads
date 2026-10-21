<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, Model $subject, array $metadata = []): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject_type' => class_basename($subject), 'subject_id' => is_numeric($subject->getKey()) ? (int) $subject->getKey() : null, 'metadata' => $metadata, 'created_at' => now()]);
    }
}
