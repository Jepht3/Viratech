<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Notifications push Firebase (FCM HTTP v1). Les réglages (identifiant du projet Google et fichier du compte de service)
 * sont saisis par l'administrateur dans les paramètres ; le fichier est chiffré en base.
 */
class FcmClient
{
    public function configured(): bool
    {
        return filled(Setting::get('push.fcm_project_id')) && filled(Setting::get('push.fcm_service_account'));
    }

    public function send(User $user, string $title, string $body, array $data = []): void
    {
        if (! $this->configured() || ! $user->notify_push) {
            return;
        }
        $tokens = DeviceToken::where('user_id', $user->id)->get();
        if ($tokens->isEmpty()) {
            return;
        }
        $access = $this->accessToken();
        $project = Setting::get('push.fcm_project_id');

        foreach ($tokens as $t) {
            $r = Http::withToken($access)->acceptJson()->timeout(15)->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", [
                'message' => [
                    'token' => $t->token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'viratech']],
                ],
            ]);
            // Jeton périmé (application désinstallée) : on le supprime.
            if (in_array($r->status(), [404, 400], true) && str_contains((string) $r->body(), 'UNREGISTERED')) {
                $t->delete();
            }
        }
    }

    /** Jeton d'accès Google (OAuth, compte de service) valable 1 h, gardé en cache. */
    private function accessToken(): string
    {
        return Cache::remember('fcm_access_token', 3000, function () {
            $sa = json_decode((string) Setting::get('push.fcm_service_account'), true);
            if (! is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
                throw new \RuntimeException('Fichier du compte de service Firebase invalide.');
            }
            $now = time();
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ]));
            openssl_sign($unsigned, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256);
            $r = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned.'.'.$b64($sig),
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new \RuntimeException('Google a refusé la connexion du compte de service.');
            }

            return (string) $r->json('access_token');
        });
    }
}