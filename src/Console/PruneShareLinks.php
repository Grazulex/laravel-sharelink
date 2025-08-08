<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Console;

use Grazulex\ShareLink\Events\ShareLinkExpired;
use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Console\Command;

class PruneShareLinks extends Command
{
    protected $signature = 'sharelink:prune {--days=0 : Only prune revoked older than N days (0 = all)}';

    protected $description = 'Delete expired or revoked share links.';

    public function handle(): int
    {
        $expiredModels = ShareLink::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expiredModels as $model) {
            event(new ShareLinkExpired($model));
        }

        $expired = ShareLink::query()
            ->whereKey($expiredModels->modelKeys())
            ->delete();

        $query = ShareLink::query()->whereNotNull('revoked_at');
        $days = (int) $this->option('days');
        if ($days > 0) {
            $query->where('revoked_at', '<', now()->subDays($days));
        }
        $revoked = $query->delete();

        $this->info("Pruned {$expired} expired and {$revoked} revoked links.");

        return self::SUCCESS;
    }
}
