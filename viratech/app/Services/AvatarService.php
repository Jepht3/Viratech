<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Photo de profil : recadrée au carré et réduite (400 px) pour garder des fichiers légers. */
class AvatarService
{
    public function store(User $user, UploadedFile $file): void
    {
        $src = match ($file->getMimeType()) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : false,
            default => false,
        };
        if (! $src) {
            throw new InvalidArgumentException('Format de photo non pris en charge (JPEG, PNG ou WebP).');
        }
        $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
        $out = imagecreatetruecolor(400, 400);
        imagecopyresampled($out, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 400, 400, $side, $side);
        ob_start();
        imagejpeg($out, null, 86);
        $jpeg = (string) ob_get_clean();

        if ($user->avatar_path) {
            Storage::delete($user->avatar_path);
        }
        $path = 'avatars/'.$user->id.'-'.substr(md5($jpeg), 0, 8).'.jpg';
        Storage::put($path, $jpeg);
        $user->update(['avatar_path' => $path]);
    }
}