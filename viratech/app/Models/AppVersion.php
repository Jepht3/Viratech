<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['notes' => 'array'];
    }

    public static function latestPublished(): ?self
    {
        return static::orderByDesc('build')->first();
    }
}
