<?php

namespace App\Notifications\Channels;

use App\Services\FcmClient;
use Illuminate\Notifications\Notification;

/** Canal « push » : envoie la notification sur les téléphones du client (barre de notification Android). */
class FcmChannel
{
    public function __construct(private FcmClient $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        try {
            $d = $notification->toArray($notifiable);
            $this->fcm->send($notifiable, (string) ($d['title'] ?? 'Viratech'), (string) ($d['body'] ?? ''), ['reference' => (string) ($d['reference'] ?? ''), 'url' => (string) ($d['url'] ?? '')]);
        } catch (\Throwable $e) {
            report($e); // une panne de push ne doit jamais bloquer une commande
        }
    }
}