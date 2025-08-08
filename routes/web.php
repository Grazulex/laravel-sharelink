<?php

declare(strict_types=1);

use Grazulex\ShareLink\Http\Controllers\ManageShareLinkController;
use Grazulex\ShareLink\Http\Controllers\ShareLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('sharelink.route.middleware', []))
    ->get(config('sharelink.route.prefix').'/{token}', [ShareLinkController::class, 'show'])
    ->name('sharelink.show');

if (config('sharelink.management.enabled', false)) {
    Route::prefix(config('sharelink.route.prefix'))
        ->middleware(config('sharelink.management.middleware', []))
        ->group(function (): void {
            Route::post('{token}/revoke', [ManageShareLinkController::class, 'revoke']);
            Route::post('{token}/extend', [ManageShareLinkController::class, 'extend']);
        });
}
