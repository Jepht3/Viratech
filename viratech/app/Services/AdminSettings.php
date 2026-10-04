<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

/** Réglages de l'administrateur : FlexPay, emails (Resend) et notifications push (Google / Firebase). */
class AdminSettings
{
    /** Réglages avec leur type : text | bool | secret | select | json. Les secrets ne sont jamais renvoyés en clair. */
    public const FIELDS = [
        'flexpay.enabled' => 'bool',
        'flexpay.environment' => 'select',
        'flexpay.merchant' => 'text',
        'flexpay.token' => 'secret',
        'flexpay.payout_enabled' => 'bool',
        'mail.resend_key' => 'secret',
        'mail.from_address' => 'text',
        'mail.from_name' => 'text',
        'push.fcm_project_id' => 'text',
        'push.fcm_service_account' => 'secret',
    ];

    public function view(): array
    {
        $out = [];
        foreach (self::FIELDS as $key => $type) {
            $out[$key] = match ($type) {
                'secret' => ['set' => Setting::get($key) !== null, 'masked' => $key === 'push.fcm_service_account' ? (Setting::get($key) ? 'Fichier enregistré' : null) : Setting::masked($key)],
                'bool' => Setting::bool($key),
                default => Setting::get($key),
            };
        }
        $out['flexpay.environment'] = Setting::get('flexpay.environment', 'sandbox');
        $out['flexpay.callback_url'] = url('/api/webhooks/flexpay?secret='.Setting::get('flexpay.callback_secret', '(générée à l\'enregistrement)'));

        return $out;
    }

    /** Enregistre. Un secret laissé vide est conservé tel quel ; « effacer » le supprime. */
    public function save(array $input): void
    {
        foreach (self::FIELDS as $key => $type) {
            $formKey = str_replace('.', '_', $key);
            $has = array_key_exists($formKey, $input) || array_key_exists($key, $input);
            $val = $input[$formKey] ?? $input[$key] ?? null;
            if ($type === 'bool') {
                Setting::set($key, ($input[$formKey] ?? $input[$key] ?? false) ? '1' : '0');
            } elseif ($type === 'secret') {
                if ($has && filled($val)) {
                    Setting::set($key, trim((string) $val), true);
                }
            } elseif ($has) {
                Setting::set($key, is_string($val) ? trim($val) : $val);
            }
        }
        if (! Setting::get('flexpay.callback_secret')) {
            Setting::set('flexpay.callback_secret', Str::random(40), true);
        }
        \Illuminate\Support\Facades\Cache::forget('fcm_access_token');
    }

    public function clear(string $key): void
    {
        if (isset(self::FIELDS[$key])) {
            Setting::set($key, null, self::FIELDS[$key] === 'secret');
        }
    }
}