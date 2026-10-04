<?php

namespace App\Models;

use App\Services\LimitPolicy;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token', 'email_code'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_code_expires_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
        ];
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['operator', 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(PayoutMethod::class);
    }

    public function kycSubmissions(): HasMany
    {
        return $this->hasMany(KycSubmission::class);
    }

    /** Plafond mensuel en USD (niveau de vérification + montée automatique avec les commandes terminées). */
    public function monthlyLimit(): ?float
    {
        return app(LimitPolicy::class)->limit($this);
    }

    public function limitInfo(): array
    {
        return app(LimitPolicy::class)->describe($this);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    }
}
