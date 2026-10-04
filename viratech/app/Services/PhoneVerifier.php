<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/** Vérification du numéro de téléphone par code à 6 chiffres. */
class PhoneVerifier
{
    public function __construct(private SmsSender $sms) {}

    /** Envoie un code. Renvoie le code en clair uniquement si la configuration locale l'autorise (tests sans SMS). */
    public function send(User $user): ?string
    {
        if (blank($user->phone)) {
            throw new InvalidArgumentException('Ajoutez d\'abord votre numéro de téléphone.');
        }
        if ($user->phone_verified_at) {
            throw new InvalidArgumentException('Ce numéro est déjà vérifié.');
        }
        // Pas plus d'un code par minute (limite l'envoi de SMS en rafale).
        if ($user->phone_code_expires_at && $user->phone_code_expires_at->subMinutes(config('viratech.phone.code_minutes') - 1)->isFuture()) {
            throw new InvalidArgumentException('Un code vient d\'être envoyé. Patientez une minute avant d\'en demander un autre.');
        }

        $code = (string) random_int(100000, 999999);
        $user->update([
            'phone_code' => Hash::make($code),
            'phone_code_expires_at' => now()->addMinutes(config('viratech.phone.code_minutes')),
            'phone_code_attempts' => 0,
        ]);
        $this->sms->send($user->phone, 'Viratech : votre code de vérification est '.$code.'. Il expire dans '.config('viratech.phone.code_minutes').' minutes. Ne le partagez jamais.');

        return config('viratech.phone.show_dev_code') ? $code : null;
    }

    public function verify(User $user, string $code): void
    {
        if (! $user->phone_code || ! $user->phone_code_expires_at || $user->phone_code_expires_at->isPast()) {
            throw new InvalidArgumentException('Ce code a expiré. Demandez-en un nouveau.');
        }
        if ($user->phone_code_attempts >= config('viratech.phone.max_attempts')) {
            throw new InvalidArgumentException('Trop d\'essais. Demandez un nouveau code.');
        }
        if (! Hash::check(trim($code), $user->phone_code)) {
            $user->increment('phone_code_attempts');
            throw new InvalidArgumentException('Code incorrect.');
        }

        $user->update([
            'phone_verified_at' => now(), 'phone_code' => null, 'phone_code_expires_at' => null, 'phone_code_attempts' => 0,
            'kyc_level' => max(1, (int) $user->kyc_level),
        ]);
    }
}
