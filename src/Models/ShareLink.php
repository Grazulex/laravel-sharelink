<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property array|string $resource
 * @property string $token
 * @property string|null $password
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $first_access_at
 * @property \Illuminate\Support\Carbon|null $last_access_at
 * @property string|null $last_ip
 * @property int|null $max_clicks
 * @property int $click_count
 * @property int $download_count
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property array|null $metadata
 */
class ShareLink extends Model
{
    use HasUuids;

    protected $table = 'share_links';

    protected $fillable = [
        'resource', 'token', 'password', 'expires_at', 'max_clicks', 'click_count',
        'download_count', 'revoked_at', 'metadata',
    ];

    protected $casts = [
        'resource' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'first_access_at' => 'datetime',
        'last_access_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Get the fillable attributes including created_by if user tracking is enabled.
     */
    public function getFillable(): array
    {
        $fillable = $this->fillable;

        if (config('sharelink.user_tracking.enabled', false)) {
            $fillable[] = 'created_by';
        }

        return $fillable;
    }

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

    /**
     * Whether the underlying table has the download_count column
     * (installations that did not run the upgrade migration do not).
     */
    public function hasDownloadCounter(): bool
    {
        if (array_key_exists('download_count', $this->attributes)) {
            return true;
        }

        return Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), 'download_count');
    }

    /**
     * Number of actual file downloads (0 when the column is not available).
     */
    public function downloadCount(): int
    {
        return (int) ($this->attributes['download_count'] ?? 0);
    }

    /**
     * Increment the download counter. No-op when the column is not available.
     */
    public function incrementDownloads(): void
    {
        if (! $this->exists || ! $this->hasDownloadCounter()) {
            return;
        }

        if (! array_key_exists('download_count', $this->attributes)) {
            // Freshly created instance: load the current value without marking it dirty
            $this->attributes['download_count'] = (int) static::query()->whereKey($this->getKey())->value('download_count');
            $this->syncOriginalAttribute('download_count');
        }

        $this->increment('download_count');
    }

    /**
     * Effective download limit: per-link metadata "max_downloads" first, then the
     * global "sharelink.limits.max_downloads" option. Null or 0 means unlimited.
     */
    public function maxDownloads(): ?int
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $value = array_key_exists('max_downloads', $metadata) && $metadata['max_downloads'] !== null
            ? $metadata['max_downloads']
            : config('sharelink.limits.max_downloads');

        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    public function hasReachedDownloadLimit(): bool
    {
        $max = $this->maxDownloads();

        return $max !== null && $this->downloadCount() >= $max;
    }

    public function markAccessed(?string $ip): void
    {
        if ($this->first_access_at === null) {
            $this->first_access_at = now();
        }

        $this->last_access_at = now();
        $this->last_ip = $ip;
        $this->save();
    }

    public function getUrlAttribute(): string
    {
        return \Illuminate\Support\Facades\URL::to('/share/'.$this->token);
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
