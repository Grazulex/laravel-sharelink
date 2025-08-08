<?php

declare(strict_types=1);

use Grazulex\ShareLink\Http\Controllers\ShareLinkController;
use Grazulex\ShareLink\Http\Middleware\EnsureShareLinkIsValid;
use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware([EnsureShareLinkIsValid::class])
        ->get('/test-share/{token}', [ShareLinkController::class, 'show']);
});

it('serves a local file with proper headers', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'slk');
    file_put_contents($tmp, str_repeat('A', 1024)); // 1KB
    $mime = function_exists('mime_content_type') ? (mime_content_type($tmp) ?: 'application/octet-stream') : 'application/octet-stream';
    $size = filesize($tmp);

    $model = ShareLink::create([
        'resource' => $tmp,
        'token' => 'filetoken-'.bin2hex(random_bytes(6)),
        'click_count' => 0,
    ]);

    $response = $this->get('/test-share/'.$model->token);

    $response->assertOk();
    $ctype = $response->headers->get('Content-Type');
    expect($ctype)->toStartWith($mime);
    $cache = $response->headers->get('Cache-Control');
    expect($cache)->toContain('no-store');
    $response->assertHeader('Content-Length', (string) $size);

    @unlink($tmp);
});

it('can signal X-Sendfile for local files', function (): void {
    Config::set('sharelink.delivery.x_sendfile', true);
    $tmp = tempnam(sys_get_temp_dir(), 'slk');
    file_put_contents($tmp, 'abc');

    $model = ShareLink::create([
        'resource' => $tmp,
        'token' => 'xsend-'.bin2hex(random_bytes(6)),
        'click_count' => 0,
    ]);

    $response = $this->get('/test-share/'.$model->token);
    $response->assertOk();
    $response->assertHeader('X-Sendfile', $tmp);
    @unlink($tmp);
});

it('can signal X-Accel-Redirect for local files', function (): void {
    Config::set('sharelink.delivery.x_sendfile', false);
    Config::set('sharelink.delivery.x_accel_redirect', '/protected');
    $tmp = tempnam(sys_get_temp_dir(), 'slk');
    file_put_contents($tmp, 'abc');

    $model = ShareLink::create([
        'resource' => $tmp,
        'token' => 'xaccel-'.bin2hex(random_bytes(6)),
        'click_count' => 0,
    ]);

    $response = $this->get('/test-share/'.$model->token);
    $response->assertOk();
    $response->assertHeader('X-Accel-Redirect');
    @unlink($tmp);
});
