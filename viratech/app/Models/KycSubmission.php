<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['flags' => 'array', 'reviewed_at' => 'datetime'];
    }

    public const ID_TYPES = [
        'carte_electeur' => "Carte d'électeur",
        'passeport' => 'Passeport',
    ];

    public const STATUSES = ['draft' => 'Selfie attendu', 'pending' => 'En cours de vérification', 'approved' => 'Approuvée', 'rejected' => 'Refusée'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function idTypeLabel(): string
    {
        return self::ID_TYPES[$this->id_type] ?? $this->id_type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
