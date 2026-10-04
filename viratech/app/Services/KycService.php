<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\KycChallenge;
use App\Models\KycSubmission;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Vérification d'identité.
 *
 * Contre la fraude : (1) le téléphone doit être vérifié ; (2) un code éphémère est remis au client, qu'il doit écrire sur papier
 * et tenir sur son selfie avec sa pièce : une ancienne photo ne contient pas le bon code ; (3) les images sont comparées à toutes
 * les autres (empreinte SHA-256) pour repérer une photo déjà utilisée par un autre compte ; (4) l'équipe valide à la main
 * en comparant le visage, la pièce et le code. Rien n'est approuvé automatiquement.
 */
class KycService
{
    /** Code à écrire sur papier. Réutilise le code encore valide pour ne pas en créer à chaque affichage. */
    public function challenge(User $user): KycChallenge
    {
        $existing = $user->hasMany(KycChallenge::class)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if ($existing) {
            return $existing;
        }

        return KycChallenge::create([
            'user_id' => $user->id,
            'code' => (string) random_int(10000, 99999),
            'expires_at' => now()->addMinutes(config('viratech.kyc.challenge_minutes')),
        ]);
    }

    public function submit(User $user, string $idType, UploadedFile $selfie, UploadedFile $idFront, ?UploadedFile $idBack = null): KycSubmission
    {
        if (! $user->phone_verified_at) {
            throw new InvalidArgumentException('Vérifiez d\'abord votre numéro de téléphone.');
        }
        if ((int) $user->kyc_level >= 2) {
            throw new InvalidArgumentException('Votre identité est déjà vérifiée.');
        }
        if ($user->kycSubmissions()->where('status', 'pending')->exists()) {
            throw new InvalidArgumentException('Votre dossier est déjà en cours de vérification.');
        }
        if ($user->kycSubmissions()->where('created_at', '>', now()->subDay())->count() >= config('viratech.kyc.max_submissions_per_day')) {
            throw new InvalidArgumentException('Trop de tentatives aujourd\'hui. Réessayez demain.');
        }
        if (! array_key_exists($idType, KycSubmission::ID_TYPES)) {
            throw new InvalidArgumentException('Type de pièce inconnu.');
        }
        $challenge = $user->hasMany(KycChallenge::class)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $challenge) {
            throw new InvalidArgumentException('Le code de sécurité a expiré. Demandez-en un nouveau et reprenez les photos.');
        }

        $hashes = [
            'selfie' => hash_file('sha256', $selfie->getRealPath()),
            'front' => hash_file('sha256', $idFront->getRealPath()),
            'back' => $idBack ? hash_file('sha256', $idBack->getRealPath()) : null,
        ];

        $flags = [];
        if ($hashes['selfie'] === $hashes['front'] || ($hashes['back'] && in_array($hashes['back'], [$hashes['selfie'], $hashes['front']], true))) {
            $flags[] = 'La même image est utilisée pour plusieurs photos.';
        }
        $used = KycSubmission::where('user_id', '!=', $user->id)->where(function ($q) use ($hashes) {
            foreach (array_filter($hashes) as $h) {
                $q->orWhere('selfie_hash', $h)->orWhere('id_front_hash', $h)->orWhere('id_back_hash', $h);
            }
        })->exists();
        if ($used) {
            $flags[] = 'Une de ces photos a déjà été envoyée par un autre compte.';
        }
        foreach (['selfie' => $selfie, 'pièce' => $idFront] as $label => $file) {
            $info = @getimagesize($file->getRealPath());
            if ($info && min($info[0], $info[1]) < 600) {
                $flags[] = 'Photo '.($label === 'selfie' ? 'du selfie' : 'de la pièce').' de faible résolution.';
            }
            if (function_exists('exif_read_data') && in_array($file->getMimeType(), ['image/jpeg'], true)) {
                $exif = @exif_read_data($file->getRealPath());
                $taken = $exif['DateTimeOriginal'] ?? null;
                if ($taken && ($t = strtotime(str_replace(':', '-', substr($taken, 0, 10)).substr($taken, 10))) && $t < now()->subHours(2)->getTimestamp()) {
                    $flags[] = 'La photo '.($label === 'selfie' ? 'du selfie' : 'de la pièce').' a été prise il y a plus de 2 heures.';
                }
            }
        }

        $dir = 'kyc/'.$user->id.'/'.Str::uuid();
        $submission = KycSubmission::create([
            'user_id' => $user->id, 'status' => 'pending', 'id_type' => $idType, 'challenge_code' => $challenge->code,
            'selfie_path' => Storage::putFile($dir, $selfie), 'id_front_path' => Storage::putFile($dir, $idFront),
            'id_back_path' => $idBack ? Storage::putFile($dir, $idBack) : null,
            'selfie_hash' => $hashes['selfie'], 'id_front_hash' => $hashes['front'], 'id_back_hash' => $hashes['back'],
            'flags' => $flags ?: null,
        ]);
        $challenge->update(['used_at' => now()]);

        User::whereIn('role', ['operator', 'admin'])->where('is_active', true)->get()->each(function (User $s) use ($user) {
            try {
                $s->notify(new Notice('Vérification d\'identité à traiter', $user->name.' a envoyé son dossier.', url('/admin/verifications')));
            } catch (\Throwable $e) {
                report($e);
            }
        });
        AuditLog::record($user, 'kyc.submitted', $submission, null, ['flags' => $flags]);

        return $submission;
    }

    public function approve(KycSubmission $s, User $reviewer): void
    {
        $this->guardPending($s);
        $s->update(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'granted_level' => 2, 'rejection_reason' => null]);
        $s->user->update(['kyc_level' => max(2, (int) $s->user->kyc_level)]);
        $limit = $s->user->fresh()->monthlyLimit();
        $this->tell($s->user, 'Identité vérifiée', 'Votre identité est vérifiée. Votre plafond mensuel est maintenant de '.number_format((float) $limit, 0, ',', ' ').' $. Il augmente aussi avec vos commandes réussies.');
        AuditLog::record($reviewer, 'kyc.approved', $s, null, ['user' => $s->user_id]);
    }

    public function reject(KycSubmission $s, User $reviewer, string $reason): void
    {
        $this->guardPending($s);
        $s->update(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'rejection_reason' => $reason]);
        $this->tell($s->user, 'Vérification refusée', $reason.' Vous pouvez envoyer un nouveau dossier.');
        AuditLog::record($reviewer, 'kyc.rejected', $s, null, ['reason' => $reason]);
    }

    private function guardPending(KycSubmission $s): void
    {
        if ($s->status !== 'pending') {
            throw new InvalidArgumentException('Ce dossier a déjà été traité.');
        }
    }

    private function tell(User $user, string $title, string $body): void
    {
        try {
            $user->notify(new Notice($title, $body, url('/profil')));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
