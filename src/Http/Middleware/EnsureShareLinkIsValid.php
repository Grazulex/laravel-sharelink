<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Http\Middleware;

use Closure;
use Grazulex\ShareLink\Events\ShareLinkExpired;
use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class EnsureShareLinkIsValid
{
    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->route('token');
        /** @var ShareLink|null $model */
        $model = ShareLink::query()->where('token', (string) $token)->first();

        if (! $model) {
            return Response::json([
                'status' => 410,
                'code' => 'sharelink.invalid',
                'title' => 'Share link is no longer valid',
                'detail' => 'The link is expired or revoked.',
            ], 410);
        }

        if ($model->isRevoked()) {
            return Response::json([
                'status' => 410,
                'code' => 'sharelink.invalid',
                'title' => 'Share link is no longer valid',
                'detail' => 'The link is expired or revoked.',
            ], 410);
        }

        if ($model->isExpired()) {
            event(new ShareLinkExpired($model));

            return Response::json([
                'status' => 410,
                'code' => 'sharelink.invalid',
                'title' => 'Share link is no longer valid',
                'detail' => 'The link is expired or revoked.',
            ], 410);
        }

        if ($model->max_clicks && $model->click_count >= $model->max_clicks) {
            return Response::json([
                'status' => 429,
                'code' => 'sharelink.limit_reached',
                'title' => 'Usage limit reached',
                'detail' => 'This link has reached its maximum number of clicks.',
            ], 429);
        }

        // Global and per-link IP filtering
        $clientIp = (string) ($request->ip() ?? '');
        $globalAllow = (array) config('sharelink.limits.ip.allow', []);
        $globalDeny = (array) config('sharelink.limits.ip.deny', []);
        $metaAllow = (array) ($model->metadata['ip_allow'] ?? []);
        $metaDeny = (array) ($model->metadata['ip_deny'] ?? []);

        $allowList = array_values(array_filter(array_merge($globalAllow, $metaAllow), static fn ($v) => is_string($v) && $v !== ''));
        $denyList = array_values(array_filter(array_merge($globalDeny, $metaDeny), static fn ($v) => is_string($v) && $v !== ''));

        $inList = function (string $ip, array $list): bool {
            foreach ($list as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }
                if (Str::contains($entry, '/')) {
                    // CIDR
                    if (self::ipInCidr($ip, $entry)) {
                        return true;
                    }
                } else {
                    if ($ip === $entry) {
                        return true;
                    }
                }
            }
            return false;
        };

        if ($clientIp !== '') {
            if ($inList($clientIp, $denyList)) {
                return Response::json([
                    'status' => 403,
                    'code' => 'sharelink.ip_denied',
                    'title' => 'Access denied',
                    'detail' => 'Your IP is not allowed to access this link.',
                ], 403);
            }
            if ($allowList !== [] && ! $inList($clientIp, $allowList)) {
                return Response::json([
                    'status' => 403,
                    'code' => 'sharelink.ip_denied',
                    'title' => 'Access denied',
                    'detail' => 'Your IP is not allowed to access this link.',
                ], 403);
            }
        }

        // Signed URL validation
        $signedEnabled = (bool) config('sharelink.signed.enabled', true);
        $signedRequired = (bool) config('sharelink.signed.required', false);
        if ($signedEnabled) {
            $isSigned = $request->hasValidSignature();
            if ($signedRequired && ! $isSigned) {
                return Response::json([
                    'status' => 403,
                    'code' => 'sharelink.signature_required',
                    'title' => 'Signature required',
                    'detail' => 'This link must be accessed with a valid signature.',
                ], 403);
            }
            // If signature params are present but invalid, reject
            if (($request->query('_signature') || $request->query('signature')) && ! $isSigned) {
                return Response::json([
                    'status' => 403,
                    'code' => 'sharelink.signature_invalid',
                    'title' => 'Invalid or expired signature',
                    'detail' => 'The signature is invalid or expired.',
                ], 403);
            }
        }

        // Per-token rate limiting
        $rateEnabled = (bool) config('sharelink.limits.rate.enabled', false);
        if ($rateEnabled) {
            $key = 'sharelink:rate:'.$model->token.':'.($request->ip() ?? 'unknown');
            $max = (int) config('sharelink.limits.rate.max', 60);
            $decay = (int) config('sharelink.limits.rate.decay', 60);
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $retry = RateLimiter::availableIn($key);

                return Response::json([
                    'status' => 429,
                    'code' => 'sharelink.rate_limited',
                    'title' => 'Too many requests',
                    'detail' => 'Try again in '.$retry.' seconds.',
                ], 429);
            }
            RateLimiter::hit($key, $decay);
        }

        $request->attributes->set('sharelink', $model);

        return $next($request);
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if ($ip === '' || $cidr === '') {
            return false;
        }
        if (! Str::contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int) $mask;
        // Only IPv4 support for now
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $maskLong = -1 << (32 - $mask);
        $ipNet = $ipLong & $maskLong;
        $subnetNet = $subnetLong & $maskLong;
        return $ipNet === $subnetNet;
    }
}
