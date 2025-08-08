<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Services;

use Grazulex\ShareLink\Events\ShareLinkRevoked;
use Grazulex\ShareLink\Models\ShareLink;

class ShareLinkRevoker
{
    public function revoke(ShareLink $model): ShareLink
    {
        $model->revoked_at = now();
        $model->save();
        event(new ShareLinkRevoked($model));

        return $model;
    }
}
