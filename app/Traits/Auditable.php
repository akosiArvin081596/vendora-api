<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::log(
                'create',
                $model,
                null,
                $model->getAttributes()
            );
        });

        static::updated(function ($model) {
            $oldValues = collect($model->getOriginal())
                ->only(array_keys($model->getDirty()))
                ->toArray();

            $newValues = $model->getDirty();

            if (! empty($newValues)) {
                AuditLog::log(
                    'update',
                    $model,
                    $oldValues,
                    $newValues
                );
            }
        });

        static::deleted(function ($model) {
            AuditLog::log(
                'delete',
                $model,
                $model->getAttributes(),
                null
            );
        });
    }
}
