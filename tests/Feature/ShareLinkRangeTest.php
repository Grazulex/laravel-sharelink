<?php

declare(strict_types=1);

use Grazulex\ShareLink\Http\Controllers\ShareLinkController;
use Grazulex\ShareLink\Http\Middleware\EnsureShareLinkIsValid;
use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware([EnsureShareLinkIsValid::class])
        ->get('/test-share/{token}', [ShareLinkController::class, 'show']);
});

it('supports HTTP Range requests for local files', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'slk');
    $data = random_bytes(4096);
    file_put_contents($tmp, $data);
    $size = filesize($tmp);

    $model = ShareLink::create([
        'resource' => $tmp,
        'token' => 'range-'.bin2hex(random_bytes(6)),
        'click_count' => 0,
    ]);

    // Request middle 1000 bytes
    $start = 500;
    $end = 1499;
    $response = $this->withHeaders(['Range' => 'bytes='.$start.'-'.$end])
        ->get('/test-share/'.$model->token);

    $response->assertStatus(206);
    $response->assertHeader('Accept-Ranges', 'bytes');
    $response->assertHeader('Content-Range', 'bytes '.$start.'-'.$end.'/'.$size);
    $response->assertHeader('Content-Length', (string) (1000));

    // Body is streamed; not asserting raw content bytes here

    @unlink($tmp);
});
