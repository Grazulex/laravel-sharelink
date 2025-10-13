<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Services;

use DateTimeInterface;
use Grazulex\ShareLink\Events\ShareLinkCreated;
use Grazulex\ShareLink\Models\ShareLink as ShareLinkModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

class ShareLinkManager
{
    /**
     * Create a new share link for a resource.
     *
     * @param  string|array<string, mixed>  $resource  File path or array (route/model definition)
     */
    public function create(string|array $resource): PendingShareLink
    {
        return new PendingShareLink($resource);
    }

    public function resolveUrl(ShareLinkModel $model): string
    {
        return URL::to('/share/'.$model->token);
    }

    public function signedUrl(ShareLinkModel $model, ?int $minutes = null): string
    {
        $params = ['token' => $model->token];
        $ttl = $minutes ?? (int) config('sharelink.signed.ttl', 15);

        return URL::temporarySignedRoute('sharelink.show', now()->addMinutes($ttl), $params);
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

    /**
     * @param  string|array<string, mixed>  $resource  File path or array (route/model definition)
     */
    public function __construct(string|array $resource)
    {
        if (is_array($resource)) {
            $this->validateArrayResource($resource);
        }

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

    public function burnAfterReading(bool $on = true): self
    {
        if ($on) {
            $this->data['max_clicks'] = 1;
            $key = (string) config('sharelink.burn.flag_key', 'burn_after_reading');
            $meta = (array) ($this->data['metadata'] ?? []);
            $meta[$key] = true;
            $this->data['metadata'] = $meta;
        }

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

    /**
     * Validate array resource structure.
     *
     * @param  array<string, mixed>  $resource
     *
     * @throws InvalidArgumentException
     */
    private function validateArrayResource(array $resource): void
    {
        $type = $resource['type'] ?? null;

        if ($type === null) {
            throw new InvalidArgumentException('Array resource must have a "type" key.');
        }

        if ($type === 'route') {
            if (! isset($resource['name']) || ! is_string($resource['name']) || $resource['name'] === '') {
                throw new InvalidArgumentException('Route resource must have a non-empty "name" key.');
            }
            if (isset($resource['params']) && ! is_array($resource['params'])) {
                throw new InvalidArgumentException('Route resource "params" must be an array.');
            }
        } elseif ($type === 'model') {
            if (! isset($resource['class']) || ! is_string($resource['class']) || $resource['class'] === '') {
                throw new InvalidArgumentException('Model resource must have a non-empty "class" key.');
            }
            if (! isset($resource['id'])) {
                throw new InvalidArgumentException('Model resource must have a non-null "id" key.');
            }
        } else {
            throw new InvalidArgumentException('Array resource type must be "route" or "model". Got: '.$type);
        }
    }
}
