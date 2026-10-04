<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/** Hors ligne : le message est écrit dans storage/logs/laravel.log au lieu d'être envoyé. */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('[SMS simulé] '.$phone.' : '.$message);
    }
}
