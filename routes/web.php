<?php

declare(strict_types=1);

use Grazulex\ShareLink\Http\Controllers\ShareLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('sharelink.route.middleware', []))
    ->get(config('sharelink.route.prefix').'/{token}', [ShareLinkController::class, 'show']);
