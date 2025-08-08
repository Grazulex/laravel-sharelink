<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Console;

use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Console\Command;

class ListShareLinks extends Command
{
    protected $signature = 'sharelink:list {--active : Only non-expired, non-revoked}';

    protected $description = 'List share links';

    public function handle(): int
    {
        $query = ShareLink::query()->orderByDesc('created_at');
        if ((bool) $this->option('active')) {
            $query->whereNull('revoked_at')->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        }
        $rows = $query->limit(50)->get(['token', 'resource', 'expires_at', 'revoked_at', 'click_count']);
        if ($rows->isEmpty()) {
            $this->line('No share links found');

            return self::SUCCESS;
        }
        $this->table(['Token', 'Resource', 'Expires', 'Revoked', 'Clicks'], $rows->map(function (ShareLink $m): array {
            return [
                $m->token,
                is_array($m->resource) ? json_encode($m->resource) : (string) $m->resource,
                optional($m->expires_at)->toDateTimeString(),
                optional($m->revoked_at)->toDateTimeString(),
                (string) $m->click_count,
            ];
        })->all());

        return self::SUCCESS;
    }
}
