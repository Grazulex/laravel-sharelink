<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Events;

use Grazulex\ShareLink\Models\ShareLink;

class ShareLinkExpired
{
    public function __construct(public ShareLink $shareLink) {}
}
