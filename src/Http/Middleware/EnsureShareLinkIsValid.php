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
}
