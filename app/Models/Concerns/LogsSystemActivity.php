<?php

namespace App\Models\Concerns;

use App\Models\SystemLog;

trait LogsSystemActivity
{
    protected static function bootLogsSystemActivity(): void
    {
        static::created(function ($model): void {
            SystemLog::record('create', $model);
        });

        static::updated(function ($model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            SystemLog::record('update', $model, null, ['changed' => array_keys($changes)]);
        });

        static::deleted(function ($model): void {
            SystemLog::record('delete', $model);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model): void {
                SystemLog::record('restore', $model);
            });
        }
    }
}
