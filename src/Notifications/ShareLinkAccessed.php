<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Notifications;

use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the link owner on the first access, or when a click/download limit is reached.
 */
class ShareLinkAccessed extends Notification
{
    public const REASON_FIRST_ACCESS = 'first_access';

    public const REASON_CLICK_LIMIT = 'click_limit';

    public const REASON_DOWNLOAD_LIMIT = 'download_limit';

    public function __construct(public ShareLink $shareLink, public string $reason = self::REASON_FIRST_ACCESS) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return array_values((array) config('sharelink.notifications.channels', ['mail']));
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage())->subject($this->subject());

        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        return $mail;
    }

    /** @return array<string,mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'event' => $this->reason,
            'share_link_id' => $this->shareLink->id,
            'click_count' => $this->shareLink->click_count,
            'download_count' => $this->shareLink->downloadCount(),
        ];
    }

    private function subject(): string
    {
        return match ($this->reason) {
            self::REASON_CLICK_LIMIT => 'A share link reached its click limit',
            self::REASON_DOWNLOAD_LIMIT => 'A share link reached its download limit',
            default => 'A share link was opened',
        };
    }

    /** @return array<int,string> */
    private function lines(): array
    {
        $lines = match ($this->reason) {
            self::REASON_CLICK_LIMIT => ['Your share link reached its maximum number of clicks ('.$this->shareLink->max_clicks.').'],
            self::REASON_DOWNLOAD_LIMIT => ['Your share link reached its maximum number of downloads ('.$this->shareLink->maxDownloads().').'],
            default => ['Your share link was opened for the first time.'],
        };

        $lines[] = 'Link: '.$this->shareLink->id;
        if ($this->shareLink->last_access_at !== null) {
            $lines[] = 'Last access: '.$this->shareLink->last_access_at->toIso8601String();
        }

        return $lines;
    }
}
