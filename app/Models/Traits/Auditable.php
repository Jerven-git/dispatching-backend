<?php

namespace App\Models\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            static::logAudit($model, 'created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $changed = $model->getChanges();
            $original = array_intersect_key($model->getOriginal(), $changed);

            // Don't log if only timestamps changed
            $meaningful = array_diff_key($changed, array_flip(['updated_at', 'created_at']));
            if (empty($meaningful)) {
                return;
            }

            static::logAudit($model, 'updated', $original, $changed);
        });

        static::deleted(function ($model) {
            static::logAudit($model, 'deleted', $model->getOriginal(), []);
        });
    }

    private static function logAudit($model, string $action, array $oldValues, array $newValues): void
    {
        $user = auth()->user();

        // Filter out sensitive fields
        $hidden = ['password', 'remember_token'];
        $oldValues = array_diff_key($oldValues, array_flip($hidden));
        $newValues = array_diff_key($newValues, array_flip($hidden));

        AuditLog::create([
            'tenant_id' => $model->tenant_id ?? ($user?->tenant_id ?? null),
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => ! empty($oldValues) ? $oldValues : null,
            'new_values' => ! empty($newValues) ? $newValues : null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
