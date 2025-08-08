<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Console;

use Grazulex\ShareLink\Models\ShareLink;
use Grazulex\ShareLink\Services\ShareLinkRevoker;
use Illuminate\Console\Command;

class RevokeShareLink extends Command
{
    protected $signature = 'sharelink:revoke {token}';

    protected $description = 'Revoke a share link by token';

    public function handle(): int
    {
        $token = (string) $this->argument('token');
        $model = ShareLink::query()->where('token', $token)->first();
        if (! $model) {
            $this->error('Share link not found');
            return self::FAILURE;
        }
        (new ShareLinkRevoker())->revoke($model);
        $this->info('Share link revoked');
        return self::SUCCESS;
    }
}
