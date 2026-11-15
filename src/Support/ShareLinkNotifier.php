<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Support;

use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Resolves the owner of a link and sends built-in notifications to them.
 *
 * Recipient lookup order: metadata "notify" (email, or false to opt out),
 * the user referenced by created_by, then the "sharelink.notifications.to" fallback.
 */
class ShareLinkNotifier
{
    public function isEnabled(string $event): bool
    {
        return (bool) config('sharelink.notifications.enabled', false)
            && (bool) config('sharelink.notifications.events.'.$event, true);
    }

    /**
     * Send the notification to the link owner. Returns false when nobody could be notified.
     */
    public function notify(ShareLink $link, Notification $notification): bool
    {
        $recipient = $this->resolveRecipient($link);
        if ($recipient === null) {
            return false;
        }

        NotificationFacade::sendNow($recipient, $notification);

        return true;
    }

    public function resolveRecipient(ShareLink $link): ?object
    {
        $metadata = is_array($link->metadata) ? $link->metadata : [];
        $notify = $metadata['notify'] ?? null;

        if ($notify === false) {
            return null;
        }

        if (is_string($notify) && $notify !== '') {
            return $this->anonymous($notify);
        }

        $ownerId = $link->getAttribute('created_by');
        if ($ownerId !== null) {
            $class = config('sharelink.notifications.owner_model') ?? config('auth.providers.users.model');
            if (is_string($class) && class_exists($class) && method_exists($class, 'find')) {
                $owner = $class::find($ownerId);
                if (is_object($owner) && method_exists($owner, 'notify')) {
                    return $owner;
                }
            }
        }

        $fallback = config('sharelink.notifications.to');
        if (is_string($fallback) && $fallback !== '') {
            return $this->anonymous($fallback);
        }

        return null;
    }

    private function anonymous(string $email): AnonymousNotifiable
    {
        return (new AnonymousNotifiable())->route('mail', $email);
    }
}
