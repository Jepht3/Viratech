<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use App\Services\FcmClient;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Notification générale (vérification d'identité, compte) : centre de notifications + email selon les préférences. */
class Notice extends Notification
{
    public function __construct(public string $title, public string $body, public string $url, public ?string $reference = null) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->notify_push && app(FcmClient::class)->configured()) {
            $channels[] = FcmChannel::class;
        }
        if ($notifiable->notify_email && $notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Viratech · '.$this->title)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->body)
            ->action('Ouvrir Viratech', $this->url)
            ->salutation('L\'équipe Viratech');
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'reference' => $this->reference];
    }
}
