<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/** Vérification de l'adresse email par un code à 6 chiffres envoyé par email (Resend en production, Mailpit en local). */
class EmailVerifier
{
    /** Envoie un code. Renvoie le code en clair uniquement si la configuration locale l'autorise (tests). */
    public function send(User $user): ?string
    {
        if ($user->email_verified_at) {
            throw new InvalidArgumentException('Votre email est déjà vérifié.');
        }
        // Pas plus d'un code par minute.
        if ($user->email_code_expires_at && $user->email_code_expires_at->subMinutes(config('viratech.email_code.minutes') - 1)->isFuture()) {
            throw new InvalidArgumentException('Un code vient d\'être envoyé. Patientez une minute avant d\'en demander un autre.');
        }

        $code = (string) random_int(100000, 999999);
        $user->update([
            'email_code' => Hash::make($code),
            'email_code_expires_at' => now()->addMinutes(config('viratech.email_code.minutes')),
            'email_code_attempts' => 0,
        ]);

        try {
            Mail::raw("Bonjour {$user->name},\n\nVotre code de vérification Viratech est : {$code}\n\nIl expire dans ".config('viratech.email_code.minutes')." minutes. Ne le partagez jamais.\n\nL'équipe Viratech", fn ($m) => $m->to($user->email)->subject('Viratech · votre code de vérification'));
        } catch (\Throwable $e) {
            report($e);
            throw new InvalidArgumentException('L\'email n\'a pas pu être envoyé pour le moment. Réessayez dans quelques minutes.');
        }

        return config('viratech.email_code.show_dev_code') ? $code : null;
    }

    public function verify(User $user, string $code): void
    {
        if (! $user->email_code || ! $user->email_code_expires_at || $user->email_code_expires_at->isPast()) {
            throw new InvalidArgumentException('Ce code a expiré. Demandez-en un nouveau.');
        }
        if ($user->email_code_attempts >= config('viratech.email_code.max_attempts')) {
            throw new InvalidArgumentException('Trop d\'essais. Demandez un nouveau code.');
        }
        if (! Hash::check(trim($code), $user->email_code)) {
            $user->increment('email_code_attempts');
            throw new InvalidArgumentException('Code incorrect.');
        }

        $user->update(['email_verified_at' => now(), 'email_code' => null, 'email_code_expires_at' => null, 'email_code_attempts' => 0, 'kyc_level' => max(1, (int) $user->kyc_level)]);
    }
}