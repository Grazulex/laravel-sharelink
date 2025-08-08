<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Facades;

use Grazulex\ShareLink\Services\ShareLinkManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Grazulex\ShareLink\Services\PendingShareLink create(string $resource)
 */
class ShareLink extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ShareLinkManager::class;
    }
}
