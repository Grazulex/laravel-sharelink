<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Services;

use DateTimeInterface;
use Grazulex\ShareLink\Events\ShareLinkCreated;
use Grazulex\ShareLink\Events\ShareLinkRevoked;
use Grazulex\ShareLink\Models\ShareLink as ShareLinkModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class ShareLinkManager
{
    public function create(string $resource): PendingShareLink
    {
        return new PendingShareLink($resource);
    }

    public function resolveUrl(ShareLinkModel $model): string
    {
        return URL::to('/share/'.$model->token);
    }

    /**
     * Extend the expiration by N hours (or set to a specific future time if $hours is null and $until provided).
     */
    public function extend(ShareLinkModel $model, ?int $hours = null, ?DateTimeInterface $until = null): ShareLinkModel
    {
        if ($until instanceof DateTimeInterface) {
            $model->expires_at = \Illuminate\Support\Carbon::instance(\Carbon\Carbon::parse($until->format(DATE_ATOM)));
        } else {
            $add = $hours ?? 1;
            $model->expires_at = ($model->expires_at ?? now())->copy()->addHours($add);
        }
        $model->save();

        return $model;
    }
}

class PendingShareLink
{
    protected array $data;

    public function __construct(string $resource)
    {
        $this->data = [
            'resource' => $resource,
            'click_count' => 0,
        ];
    }

    public function expiresIn(int $hours = 1): self
    {
        $this->data['expires_at'] = now()->addHours($hours);

        return $this;
    }

    public function maxClicks(?int $max = null): self
    {
        $this->data['max_clicks'] = $max;

        return $this;
    }

    public function withPassword(?string $password): self
    {
        $this->data['password'] = $password !== null && $password !== '' && $password !== '0' ? Hash::make($password) : null;

        return $this;
    }

    public function metadata(array $meta): self
    {
        $this->data['metadata'] = $meta;

        return $this;
    }

    public function generate(): ShareLinkModel
    {
        if (! isset($this->data['token']) || $this->data['token'] === '') {
            $this->data['token'] = \Illuminate\Support\Str::random(32);
        }
        $model = ShareLinkModel::create($this->data);
        event(new ShareLinkCreated($model));

        return $model;
    }
}

class ShareLinkRevoker
{
    public function revoke(ShareLinkModel $model): ShareLinkModel
    {
        $model->revoked_at = now();
        $model->save();
        event(new ShareLinkRevoked($model));

        return $model;
    }
}
