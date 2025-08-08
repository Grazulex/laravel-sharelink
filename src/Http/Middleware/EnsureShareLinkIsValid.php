<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Http\Middleware;

use Closure;
use Grazulex\ShareLink\Events\ShareLinkExpired;
use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

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

        $request->attributes->set('sharelink', $model);

        return $next($request);
    }
}
