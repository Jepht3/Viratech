<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres modifiables par l'administrateur depuis le tableau de bord ou l'application Admin.
 * Les secrets (jetons, clés API) sont chiffrés en base ; la valeur d'un secret n'est jamais renvoyée en clair à l'écran.
 */
class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all_();

        return array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '' ? $all[$key] : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);

        return $v === null ? $default : in_array($v, [true, 1, '1', 'true', 'on'], true);
    }

    public static function set(string $key, mixed $value, bool $secret = false): void
    {
        $stored = ($value === null || $value === '') ? null : ($secret ? Crypt::encryptString((string) $value) : (string) $value);
        static::updateOrCreate(['key' => $key], ['value' => $stored, 'is_secret' => $secret]);
        self::$cache = null;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /** Valeur masquée pour l'affichage d'un secret (••••1234). */
    public static function masked(string $key): ?string
    {
        $v = self::get($key);

        return $v === null ? null : '••••'.substr((string) $v, -4);
    }

    private static function all_(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = [];
        try {
            if (Schema::hasTable('settings')) {
                foreach (static::all() as $s) {
                    $out[$s->key] = $s->is_secret && $s->value ? self::decrypt($s->value) : $s->value;
                }
            }
        } catch (\Throwable) {
            // base indisponible (migrations en cours) : on retombe sur les valeurs par défaut
        }

        return self::$cache = $out;
    }

    private static function decrypt(string $v): ?string
    {
        try {
            return Crypt::decryptString($v);
        } catch (\Throwable) {
            return null;
        }
    }
}