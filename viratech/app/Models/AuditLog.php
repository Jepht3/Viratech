<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['old' => 'array', 'new' => 'array', 'created_at' => 'datetime'];
    }

    public static function record(?User $user, string $action, ?Model $subject = null, ?array $old = null, ?array $new = null): self
    {
        return static::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'old' => $old,
            'new' => $new,
            'created_at' => now(),
        ]);
    }
}
