<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Http\Controllers;

use Grazulex\ShareLink\Events\ShareLinkAccessed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Grazulex\ShareLink\Http\Resources\ShareLinkResource;

class ShareLinkController
{
    public function show(Request $request, string $token)
    {
        $model = $request->attributes->get('sharelink');

        // Optional password gate
        if ($model->password) {
            $pwd = $request->input('password');
            if (! $pwd || ! Hash::check($pwd, $model->password)) {
                return ResponseFacade::json([
                    'status' => 401,
                    'code' => 'password.invalid',
                    'title' => 'Password required or invalid',
                    'detail' => 'Provide a valid password to access this resource.',
                ], 401);
            }
        }

        // Increment usage and mark audit
        $model->incrementClicks();
        $model->markAccessed($request->ip());

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
                        'X-Accel-Redirect' => rtrim($xAccel, '/').'/'.ltrim(basename($res), '/'),
                        'Content-Disposition' => (new ResponseHeaderBag())->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($res)),
                        'Cache-Control' => 'no-store',
                    ]);
                }
                $mime = \function_exists('mime_content_type') ? (mime_content_type($res) ?: 'application/octet-stream') : 'application/octet-stream';
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
                                    $chunk = fread($fh, (int) min(8192, $remaining));
                                    if ($chunk === false) {
                                        break;
                                    }
                                    echo $chunk;
                                    $remaining -= strlen($chunk);
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
                    } catch (\Throwable) {
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

        event(new ShareLinkAccessed($model));

        // Content negotiation: if client Accepts JSON, use Resource
        if ($request->wantsJson()) {
            return (new ShareLinkResource($model))->response()->setStatusCode(200);
        }

        return ResponseFacade::json((new ShareLinkResource($model))->toArray($request));
    }
}
