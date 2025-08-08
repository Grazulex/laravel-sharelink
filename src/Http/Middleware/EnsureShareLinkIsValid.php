<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Http\Middleware;

use Closure;
use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Http\Request;

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

        if (! $model || $model->isRevoked() || $model->isExpired()) {
            abort(410, 'This share link is no longer valid.');
        }

        if ($model->max_clicks && $model->click_count >= $model->max_clicks) {
            abort(429, 'This share link has reached its usage limit.');
        }

        $request->attributes->set('sharelink', $model);

        return $next($request);
    }
}
