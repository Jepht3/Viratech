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
 * Vérification d'identité en deux temps, photos prises UNIQUEMENT avec la caméra (application ou caméra du navigateur) :
 *
 *  1. le client envoie sa pièce (carte d'électeur ou passeport) ;
 *  2. puis on lui demande une photo de lui TENANT cette pièce, avec une feuille portant un code éphémère écrit à la main.
 *
 * Contre la fraude : le code éphémère (une ancienne photo ne le contient pas), l'empreinte SHA-256 des images comparée à celles
 * des autres comptes, la détection d'une même image réutilisée, les photos anciennes ou floues signalées, 3 tentatives par jour,
 * et surtout la validation manuelle par l'équipe : rien n'est approuvé automatiquement.
 */
class KycService
{
    /** Étape 1 : la pièce. Remplace un éventuel brouillon précédent. */
    public function submitDocument(User $user, string $idType, UploadedFile $front, ?UploadedFile $back = null): KycSubmission
    {
        $this->guardCanSubmit($user);
        if (! array_key_exists($idType, KycSubmission::ID_TYPES)) {
            throw new InvalidArgumentException('Choisissez la carte d\'électeur ou le passeport.');
        }
        if ($idType === 'carte_electeur' && ! $back) {
            throw new InvalidArgumentException('Prenez aussi la face arrière de la carte d\'électeur.');
        }

        $user->kycSubmissions()->where('status', 'draft')->get()->each(function (KycSubmission $d) {
            $d->delete();
        });

        $hashes = ['front' => hash_file('sha256', $front->getRealPath()), 'back' => $back ? hash_file('sha256', $back->getRealPath()) : null];
        $dir = 'kyc/'.$user->id.'/'.Str::uuid();

        return KycSubmission::create([
            'user_id' => $user->id, 'status' => 'draft', 'id_type' => $idType,
            'id_front_path' => Storage::putFile($dir, $front), 'id_back_path' => $back ? Storage::putFile($dir, $back) : null,
            'id_front_hash' => $hashes['front'], 'id_back_hash' => $hashes['back'],
            'flags' => $this->documentFlags($user, $front, $hashes),
        ]);
    }

    /** Code à écrire sur papier et à tenir sur le selfie (étape 2). Réutilise le code encore valide. */
    public function challenge(User $user): KycChallenge
    {
        $existing = KycChallenge::where('user_id', $user->id)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if ($existing) {
            return $existing;
        }

        return KycChallenge::create(['user_id' => $user->id, 'code' => (string) random_int(10000, 99999), 'expires_at' => now()->addMinutes(config('viratech.kyc.challenge_minutes'))]);
    }

    public function newChallenge(User $user): KycChallenge
    {
        KycChallenge::where('user_id', $user->id)->whereNull('used_at')->update(['expires_at' => now()]);

        return $this->challenge($user);
    }

    /** Étape 2 : le selfie avec la pièce en main. Le dossier devient « à vérifier » par l'équipe. */
    public function submitSelfie(User $user, UploadedFile $selfie): KycSubmission
    {
        $this->guardCanSubmit($user);
        $draft = $user->kycSubmissions()->where('status', 'draft')->latest('id')->first();
        if (! $draft) {
            throw new InvalidArgumentException('Envoyez d\'abord la photo de votre pièce d\'identité.');
        }
        $challenge = KycChallenge::where('user_id', $user->id)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $challenge) {
            throw new InvalidArgumentException('Le code de sécurité a expiré. Demandez-en un nouveau et reprenez le selfie.');
        }

        $hash = hash_file('sha256', $selfie->getRealPath());
        $flags = $draft->flags ?? [];
        if (in_array($hash, [$draft->id_front_hash, $draft->id_back_hash], true)) {
            $flags[] = 'Le selfie est la même image que la photo de la pièce.';
        }
        if (KycSubmission::where('user_id', '!=', $user->id)->where(fn ($q) => $q->where('selfie_hash', $hash)->orWhere('id_front_hash', $hash)->orWhere('id_back_hash', $hash))->exists()) {
            $flags[] = 'Le selfie a déjà été envoyé par un autre compte.';
        }
        array_push($flags, ...$this->imageFlags($selfie, 'du selfie'));

        $draft->update([
            'status' => 'pending', 'challenge_code' => $challenge->code, 'selfie_path' => Storage::putFile('kyc/'.$user->id.'/'.Str::uuid(), $selfie),
            'selfie_hash' => $hash, 'flags' => array_values(array_unique($flags)) ?: null,
        ]);
        $challenge->update(['used_at' => now()]);

        User::whereIn('role', ['operator', 'admin'])->where('is_active', true)->get()->each(function (User $s) use ($user) {
            try {
                $s->notify(new Notice('Vérification d\'identité à traiter', $user->name.' a envoyé son dossier.', url('/admin/verifications')));
            } catch (\Throwable $e) {
                report($e);
            }
        });
        AuditLog::record($user, 'kyc.submitted', $draft, null, ['flags' => $draft->flags]);

        return $draft->fresh();
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

    /** « none | document | pending | approved | rejected » : où en est le client. */
    public function stage(User $user): string
    {
        if ((int) $user->kyc_level >= 2) {
            return 'approved';
        }
        $last = $user->kycSubmissions()->latest('id')->first();

        return match ($last?->status) {
            'draft' => 'document',
            'pending' => 'pending',
            'rejected' => 'rejected',
            default => 'none',
        };
    }

    // ───────────── internes ─────────────

    private function guardCanSubmit(User $user): void
    {
        if (! $user->email_verified_at) {
            throw new InvalidArgumentException('Vérifiez d\'abord votre adresse email.');
        }
        if ((int) $user->kyc_level >= 2) {
            throw new InvalidArgumentException('Votre identité est déjà vérifiée.');
        }
        if ($user->kycSubmissions()->where('status', 'pending')->exists()) {
            throw new InvalidArgumentException('Votre dossier est déjà en cours de vérification.');
        }
        if ($user->kycSubmissions()->where('status', '!=', 'draft')->where('created_at', '>', now()->subDay())->count() >= config('viratech.kyc.max_submissions_per_day')) {
            throw new InvalidArgumentException('Trop de tentatives aujourd\'hui. Réessayez demain.');
        }
    }

    private function documentFlags(User $user, UploadedFile $front, array $hashes): array
    {
        $flags = [];
        if ($hashes['back'] && $hashes['back'] === $hashes['front']) {
            $flags[] = 'La face avant et la face arrière sont la même image.';
        }
        $used = KycSubmission::where('user_id', '!=', $user->id)->where(function ($q) use ($hashes) {
            foreach (array_filter($hashes) as $h) {
                $q->orWhere('selfie_hash', $h)->orWhere('id_front_hash', $h)->orWhere('id_back_hash', $h);
            }
        })->exists();
        if ($used) {
            $flags[] = 'La photo de la pièce a déjà été envoyée par un autre compte.';
        }
        array_push($flags, ...$this->imageFlags($front, 'de la pièce'));

        return $flags;
    }

    private function imageFlags(UploadedFile $file, string $label): array
    {
        $flags = [];
        $info = @getimagesize($file->getRealPath());
        if ($info && min($info[0], $info[1]) < 600) {
            $flags[] = 'Photo '.$label.' de faible résolution.';
        }
        if (function_exists('exif_read_data') && $file->getMimeType() === 'image/jpeg') {
            $exif = @exif_read_data($file->getRealPath());
            $taken = $exif['DateTimeOriginal'] ?? null;
            if ($taken && ($t = strtotime(str_replace(':', '-', substr($taken, 0, 10)).substr($taken, 10))) && $t < now()->subHours(2)->getTimestamp()) {
                $flags[] = 'La photo '.$label.' a été prise il y a plus de 2 heures.';
            }
        }

        return $flags;
    }

    private function guardPending(KycSubmission $s): void
    {
        if ($s->status !== 'pending') {
            throw new InvalidArgumentException('Ce dossier a déjà été traité ou est incomplet.');
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