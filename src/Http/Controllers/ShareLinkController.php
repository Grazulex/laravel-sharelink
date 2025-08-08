<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Http\Controllers;

use Grazulex\ShareLink\Events\ShareLinkAccessed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\Facades\Storage;

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

                return response()->download($res);
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

                    return ResponseFacade::streamDownload(function () use ($stream): void {
                        if (is_resource($stream)) {
                            stream_copy_to_stream($stream, fopen('php://output', 'wb'));
                            fclose($stream);
                        }
                    }, $filename, [
                        'Content-Type' => $mime,
                    ]);
                }
            }
        }

        event(new ShareLinkAccessed($model));

        return ResponseFacade::json([
            'token' => $model->token,
            'resource' => $model->resource,
            'metadata' => $model->metadata,
            'clicks' => $model->click_count,
        ]);
    }
}
