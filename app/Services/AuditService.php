<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\StatusHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'invitation_token',
        'api_key',
        'secret',
    ];

    public static function log(
        string $action,
        string|Model $auditable,
        ?int $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null
    ): AuditLog {
        $user = Auth::user();
        $type = $auditable instanceof Model ? get_class($auditable) : $auditable;
        $id = $auditable instanceof Model ? $auditable->getKey() : $auditableId;

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'user_role' => $user?->roleSlug() ?? 'system',
            'action' => $action,
            'auditable_type' => class_basename($type),
            'auditable_id' => $id,
            'old_values' => self::redact($oldValues),
            'new_values' => self::redact($newValues),
            'reason' => $reason,
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) request()?->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    public static function recordActivity(
        Model $subject,
        string $event,
        string $title,
        ?string $description = null,
        bool $isClientVisible = false
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'title' => $title,
            'description' => $description,
            'is_client_visible' => $isClientVisible,
            'created_at' => now(),
        ]);
    }

    public static function recordStatusTransition(
        Model $trackable,
        ?string $fromStatus,
        string $toStatus,
        ?string $reason = null,
        ?array $metadata = null
    ): StatusHistory {
        return StatusHistory::create([
            'trackable_type' => get_class($trackable),
            'trackable_id' => $trackable->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => Auth::id(),
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private static function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sanitized = [];
        foreach ($values as $key => $val) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $val;
            }
        }

        return $sanitized;
    }
}
