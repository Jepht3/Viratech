<?php

namespace App\Services;

/** Envoi de SMS (ou message WhatsApp) : un fournisseur réel viendra remplacer LogSmsSender. */
interface SmsSender
{
    public function send(string $phone, string $message): void;
}
