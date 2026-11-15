<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Notifications;

use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the link owner when a link reached its deadline without any access.
 */
class ShareLinkDeadlineMissed extends Notification
{
    public function __construct(public ShareLink $shareLink) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return array_values((array) config('sharelink.notifications.channels', ['mail']));
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('A share link expired without being opened')
            ->line('Your share link reached its deadline without any access.')
            ->line('Link: '.$this->shareLink->id)
            ->line('Deadline: '.optional($this->shareLink->expires_at)->toIso8601String());
    }

    /** @return array<string,mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'event' => 'deadline_missed',
            'share_link_id' => $this->shareLink->id,
            'expires_at' => optional($this->shareLink->expires_at)->toIso8601String(),
        ];
    }
}
