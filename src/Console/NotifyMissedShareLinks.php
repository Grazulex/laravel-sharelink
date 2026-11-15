<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Console;

use Grazulex\ShareLink\Models\ShareLink;
use Grazulex\ShareLink\Notifications\ShareLinkDeadlineMissed;
use Grazulex\ShareLink\Support\ShareLinkNotifier;
use Illuminate\Console\Command;

class NotifyMissedShareLinks extends Command
{
    protected $signature = 'sharelink:notify-missed';

    protected $description = 'Notify owners of links that expired without any access.';

    public function handle(ShareLinkNotifier $notifier): int
    {
        if (! $notifier->isEnabled('deadline_missed')) {
            $this->info('Notified 0 owners.');

            return self::SUCCESS;
        }

        $links = ShareLink::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('first_access_at')
            ->whereNull('revoked_at')
            ->get();

        $sent = 0;
        foreach ($links as $link) {
            $metadata = is_array($link->metadata) ? $link->metadata : [];
            if (isset($metadata['deadline_missed_notified_at'])) {
                continue;
            }

            if (! $notifier->notify($link, new ShareLinkDeadlineMissed($link))) {
                continue;
            }

            $metadata['deadline_missed_notified_at'] = now()->toIso8601String();
            $link->metadata = $metadata;
            $link->saveQuietly();
            $sent++;
        }

        $this->info("Notified {$sent} owners.");

        return self::SUCCESS;
    }
}
