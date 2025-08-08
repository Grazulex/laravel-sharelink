<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Events;

use Grazulex\ShareLink\Models\ShareLink;

class ShareLinkAccessed
{
    public function __construct(public ShareLink $shareLink) {}
}
