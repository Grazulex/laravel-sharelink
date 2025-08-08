<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Services;

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
        $model = ShareLinkModel::create($this->data);
        /** @var string $url */
        $url = app(ShareLinkManager::class)->resolveUrl($model);
        $model->setAttribute('url', $url);

        return $model;
    }
}
