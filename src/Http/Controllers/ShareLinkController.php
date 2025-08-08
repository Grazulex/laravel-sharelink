<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;

class ShareLinkController
{
    public function show(Request $request, string $token)
    {
        $model = $request->attributes->get('sharelink');

        // Optional password gate
        if ($model->password) {
            $pwd = $request->input('password');
            if (! $pwd || ! Hash::check($pwd, $model->password)) {
                return response()->json(['message' => 'Password required or invalid.'], 401);
            }
        }

        // Increment usage
        $model->incrementClicks();

        $res = $model->resource;
        if (is_string($res) && file_exists($res)) {
            return response()->download($res);
        }

        return Response::json([
            'token' => $model->token,
            'resource' => $model->resource,
            'metadata' => $model->metadata,
            'clicks' => $model->click_count,
        ]);
    }
}
