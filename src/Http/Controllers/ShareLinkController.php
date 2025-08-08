<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Http\Controllers;

use Grazulex\ShareLink\Events\ShareLinkAccessed;
use Grazulex\ShareLink\Http\Resources\ShareLinkResource;
use Grazulex\ShareLink\Services\ShareLinkRevoker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

use function function_exists;

class ShareLinkController
{
    public function show(Request $request, string $token)
    {
        $model = $request->attributes->get('sharelink');

        // Optional password gate with throttling
        if ($model->password) {
            $pwd = $request->input('password');
            $limitEnabled = (bool) config('sharelink.limits.password.enabled', true);
            $key = 'sharelink:pwd:'.$model->token.':'.($request->ip() ?? 'unknown');
            if ($limitEnabled && RateLimiter::tooManyAttempts($key, (int) config('sharelink.limits.password.max', 5))) {
                $retry = RateLimiter::availableIn($key);

                return ResponseFacade::json([
                    'status' => 429,
                    'code' => 'password.throttled',
                    'title' => 'Too many password attempts',
                    'detail' => 'Try again in '.$retry.' seconds.',
                ], 429);
            }

            if (! $pwd || ! Hash::check($pwd, $model->password)) {
                if ($limitEnabled) {
                    RateLimiter::hit($key, (int) config('sharelink.limits.password.decay', 600));
                }

                return ResponseFacade::json([
                    'status' => 401,
                    'code' => 'password.invalid',
                    'title' => 'Password required or invalid',
                    'detail' => 'Provide a valid password to access this resource.',
                ], 401);
            }

            if ($limitEnabled) {
                RateLimiter::clear($key);
            }
        }

        // Increment usage and mark audit
        $model->incrementClicks();
        $model->markAccessed($request->ip());

        // Burn-after-reading: revoke immediately after first successful access
        $burnEnabled = (bool) config('sharelink.burn.enabled', true);
        if ($burnEnabled) {
            $flagKey = (string) config('sharelink.burn.flag_key', 'burn_after_reading');
            $auto = (bool) config('sharelink.burn.auto_max_clicks', false);
            $isFlagged = (bool) ($model->metadata[$flagKey] ?? false);
            $isAuto = $auto && (int) ($model->max_clicks ?? 0) === 1;
            if (($isFlagged || $isAuto) && (int) $model->click_count >= 1) {
                $strategy = (string) config('sharelink.burn.strategy', 'revoke');
                if ($strategy === 'delete') {
                    // Hard delete after first access
                    $model->delete();
                } else {
                    // Default: revoke
                    (new ShareLinkRevoker())->revoke($model);
                }
            }
        }

        $res = $model->resource;
        if (is_string($res)) {
            // Local file path
            if (file_exists($res)) {
                event(new ShareLinkAccessed($model));
                // X-Sendfile / X-Accel-Redirect
                $xAccel = (string) (config('sharelink.delivery.x_accel_redirect') ?? '');
                if (config('sharelink.delivery.x_sendfile') === true) {
                    return ResponseFacade::make('', 200, [
                        'X-Sendfile' => $res,
                        'Content-Disposition' => (new ResponseHeaderBag())->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($res)),
                        'Cache-Control' => 'no-store',
                    ]);
                }
                if ($xAccel !== '') {
                    // Map real path to internal prefix if needed; here we assume res is already under the internal location
                    return ResponseFacade::make('', 200, [
                        'X-Accel-Redirect' => mb_rtrim($xAccel, '/').'/'.mb_ltrim(basename($res), '/'),
                        'Content-Disposition' => (new ResponseHeaderBag())->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($res)),
                        'Cache-Control' => 'no-store',
                    ]);
                }
                $mime = function_exists('mime_content_type') ? (mime_content_type($res) ?: 'application/octet-stream') : 'application/octet-stream';
                $size = @filesize($res) ?: null;

                // Handle HTTP Range for partial content when size known
                $rangeHeader = $request->headers->get('Range');
                if ($size !== null && is_string($rangeHeader) && str_starts_with($rangeHeader, 'bytes=')) {
                    if (preg_match('/bytes=(\d+)-(\d+)?/', $rangeHeader, $m) === 1) {
                        $start = (int) $m[1];
                        $end = isset($m[2]) ? (int) $m[2] : ($size - 1);
                        if ($start <= $end && $end < $size) {
                            $length = ($end - $start) + 1;

                            return ResponseFacade::stream(function () use ($res, $start, $length): void {
                                $fh = @fopen($res, 'rb');
                                if ($fh === false) {
                                    return;
                                }
                                @fseek($fh, $start);
                                $remaining = $length;
                                while ($remaining > 0 && ! feof($fh)) {
                                    $chunk = fread($fh, min(8192, $remaining));
                                    if ($chunk === false) {
                                        break;
                                    }
                                    echo $chunk;
                                    $remaining -= mb_strlen($chunk);
                                }
                                @fclose($fh);
                            }, 206, [
                                'Content-Type' => $mime,
                                'Content-Length' => (string) $length,
                                'Content-Range' => 'bytes '.$start.'-'.$end.'/'.$size,
                                'Accept-Ranges' => 'bytes',
                                'Cache-Control' => 'no-store',
                                'Content-Disposition' => (new ResponseHeaderBag())->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($res)),
                            ]);
                        }
                    }

                    // Invalid range -> 416
                    return ResponseFacade::make('', 416, [
                        'Content-Range' => 'bytes */'.$size,
                        'Accept-Ranges' => 'bytes',
                        'Cache-Control' => 'no-store',
                    ]);
                }

                // Full download (no Range)
                $headers = [
                    'Content-Type' => $mime,
                    'Cache-Control' => 'no-store',
                ];
                if ($size !== null) {
                    $headers['Content-Length'] = (string) $size;
                }

                $response = response()->download($res, basename($res), $headers);
                // Ensure disposition as attachment preserves filename
                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($res));

                return $response;
            }

            // Storage disk reference: disk:path/to/file
            if (str_contains($res, ':')) {
                [$disk, $path] = explode(':', $res, 2);
                $fs = Storage::disk($disk);
                if ($fs->exists($path)) {
                    event(new ShareLinkAccessed($model));
                    $mime = 'application/octet-stream';
                    $filename = basename($path);
                    $stream = $fs->readStream($path);

                    $headers = [
                        'Content-Type' => $mime,
                        'Cache-Control' => 'no-store',
                    ];
                    try {
                        $size = $fs->size($path);
                        $headers['Content-Length'] = (string) $size;
                    } catch (Throwable) {
                        // ignore if driver does not support size
                    }

                    return ResponseFacade::streamDownload(function () use ($stream): void {
                        if (is_resource($stream)) {
                            stream_copy_to_stream($stream, fopen('php://output', 'wb'));
                            fclose($stream);
                        }
                    }, $filename, $headers);
                }
            }
        }

        // Route target resource: redirect to a named route with params
        if (is_array($res)) {
            $type = (string) ($res['type'] ?? '');
            if ($type === 'route') {
                $name = (string) ($res['name'] ?? '');
                $params = (array) ($res['params'] ?? []);
                if ($name !== '') {
                    event(new ShareLinkAccessed($model));

                    return redirect()->route($name, $params);
                }
            }
            if ($type === 'model') {
                $cls = (string) ($res['class'] ?? '');
                $id = $res['id'] ?? null;
                // Defer to app-defined preview route, passing class and id
                if ($cls !== '' && $id !== null && config('app.debug')) {
                    // Fallback simple JSON when no preview route defined
                    event(new ShareLinkAccessed($model));

                    return ResponseFacade::json([
                        'status' => 200,
                        'code' => 'sharelink.model_preview',
                        'title' => 'Model preview',
                        'detail' => 'App should define a route to present this model.',
                        'model' => ['class' => $cls, 'id' => $id],
                    ], 200);
                }
            }
        }

        event(new ShareLinkAccessed($model));

        // Content negotiation: if client Accepts JSON, use Resource
        if ($request->wantsJson()) {
            return (new ShareLinkResource($model))->response()->setStatusCode(200);
        }

        return ResponseFacade::json((new ShareLinkResource($model))->toArray($request));
    }
}
