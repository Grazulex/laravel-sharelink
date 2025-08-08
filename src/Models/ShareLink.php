<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property array|string $resource
 * @property string $token
 * @property string|null $password
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property int|null $max_clicks
 * @property int $click_count
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property array|null $metadata
 */
class ShareLink extends Model
{
    use HasUuids;

    protected $table = 'share_links';

    protected $fillable = [
        'resource', 'token', 'password', 'expires_at', 'max_clicks', 'click_count',
        'revoked_at', 'metadata', 'created_by',
    ];

    protected $casts = [
        'resource' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && now()->greaterThan($this->expires_at);
    }

    public function isRevoked(): bool
    {
        return ! is_null($this->revoked_at);
    }

    public function incrementClicks(): void
    {
        $this->increment('click_count');
    }

    protected static function booted(): void
    {
        static::creating(function ($model): void {
            if (empty($model->token)) {
                $model->token = Str::random(32);
            }
        });
    }
}
