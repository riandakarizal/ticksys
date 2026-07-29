<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'loggable_type', 'loggable_id', 'description', 'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, Model $model, ?string $description = null, array $properties = []): self
    {
        $type = class_basename($model);

        return static::create([
            'user_id'       => auth()->id(),
            'action'        => $action,
            'loggable_type' => $type,
            'loggable_id'   => (string) $model->getKey(),
            'description'   => $description ?? ucfirst($action) . ' ' . $type . ' ' . $model->getKey(),
            'properties'    => $properties,
        ]);
    }
}
