<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Contracts;

interface MetricsSink
{
    /** @param array<string,mixed> $tags */
    public function increment(string $metric, int $value = 1, array $tags = []): void;

    /** @param array<string,mixed> $tags */
    public function gauge(string $metric, float|int $value, array $tags = []): void;

    /** @param array<string,mixed> $tags */
    public function timing(string $metric, float|int $ms, array $tags = []): void;
}
