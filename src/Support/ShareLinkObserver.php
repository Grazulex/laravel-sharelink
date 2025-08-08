<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Support;

use Grazulex\ShareLink\Contracts\MetricsSink;
use Grazulex\ShareLink\Events\ShareLinkAccessed;
use Grazulex\ShareLink\Events\ShareLinkCreated;
use Grazulex\ShareLink\Events\ShareLinkExpired;
use Grazulex\ShareLink\Events\ShareLinkRevoked;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class ShareLinkObserver
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ShareLinkCreated::class, [$this, 'onCreated']);
        $events->listen(ShareLinkAccessed::class, [$this, 'onAccessed']);
        $events->listen(ShareLinkRevoked::class, [$this, 'onRevoked']);
        $events->listen(ShareLinkExpired::class, [$this, 'onExpired']);
    }

    public function onCreated(ShareLinkCreated $event): void
    {
        if (! (bool) config('sharelink.observability.enabled', true)) {
            return;
        }
        $model = $event->shareLink;
        $payload = [
            'event' => 'sharelink.created',
            'resource_type' => is_array($model->resource) ? 'array' : 'string',
            'has_password' => $model->password !== null,
            'expires_at' => optional($model->expires_at)->toIso8601String(),
            'max_clicks' => $model->max_clicks,
        ];
        if ((bool) config('sharelink.observability.log', true)) {
            Log::info('ShareLink created', $payload);
        }
        $this->metric('sharelink.created', 1, ['has_password' => (int) ($model->password !== null)]);
    }

    public function onAccessed(ShareLinkAccessed $event): void
    {
        if (! (bool) config('sharelink.observability.enabled', true)) {
            return;
        }
        $model = $event->shareLink;
        $payload = [
            'event' => 'sharelink.accessed',
            'click_count' => $model->click_count,
            'first_access_at' => optional($model->first_access_at)->toIso8601String(),
            'last_access_at' => optional($model->last_access_at)->toIso8601String(),
        ];
        if ((bool) config('sharelink.observability.log', true)) {
            Log::info('ShareLink accessed', $payload);
        }
        $this->metric('sharelink.accessed', 1);
    }

    public function onRevoked(ShareLinkRevoked $event): void
    {
        if (! (bool) config('sharelink.observability.enabled', true)) {
            return;
        }
        if ((bool) config('sharelink.observability.log', true)) {
            Log::info('ShareLink revoked', ['event' => 'sharelink.revoked']);
        }
        $this->metric('sharelink.revoked', 1);
    }

    public function onExpired(ShareLinkExpired $event): void
    {
        if (! (bool) config('sharelink.observability.enabled', true)) {
            return;
        }
        if ((bool) config('sharelink.observability.log', true)) {
            Log::info('ShareLink expired', ['event' => 'sharelink.expired']);
        }
        $this->metric('sharelink.expired', 1);
    }

    /** @param array<string,mixed> $tags */
    private function metric(string $name, int $count = 1, array $tags = []): void
    {
        if (! (bool) config('sharelink.observability.metrics', false)) {
            return;
        }
        $sink = app()->bound(MetricsSink::class) ? app(MetricsSink::class) : null;
        if ($sink) {
            $sink->increment($name, $count, $tags);
        }
    }
}
