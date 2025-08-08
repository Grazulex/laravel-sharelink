<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Http\Controllers;

use Grazulex\ShareLink\Http\Resources\ShareLinkResource;
use Grazulex\ShareLink\Models\ShareLink;
use Grazulex\ShareLink\Services\ShareLinkManager;
use Grazulex\ShareLink\Services\ShareLinkRevoker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response as ResponseFacade;

class ManageShareLinkController
{
    public function revoke(Request $request, ShareLinkManager $manager, string $token)
    {
        $model = ShareLink::query()->where('token', $token)->first();
        if (! $model) {
            return ResponseFacade::json([
                'status' => 404,
                'code' => 'sharelink.not_found',
                'title' => 'Share link not found',
                'detail' => 'No link matches this token.',
            ], 404);
        }

        // Optional gate authorization
        $ability = config('sharelink.management.gate');
        if (is_string($ability) && $ability !== '') {
            if (Gate::denies($ability, $model)) {
                return ResponseFacade::json([
                    'status' => 403,
                    'code' => 'sharelink.forbidden',
                    'title' => 'Forbidden',
                    'detail' => 'You are not authorized to manage this link.',
                ], 403);
            }
        }

    $revoker = new ShareLinkRevoker();
    $revoker->revoke($model);

        return (new ShareLinkResource($model))->response();
    }

    public function extend(Request $request, ShareLinkManager $manager, string $token)
    {
        $hours = $request->integer('hours', 1);
        if ($hours <= 0) {
            return ResponseFacade::json([
                'status' => 422,
                'code' => 'sharelink.invalid_hours',
                'title' => 'Invalid hours value',
                'detail' => 'Hours must be a positive integer.',
            ], 422);
        }

        $model = ShareLink::query()->where('token', $token)->first();
        if (! $model) {
            return ResponseFacade::json([
                'status' => 404,
                'code' => 'sharelink.not_found',
                'title' => 'Share link not found',
                'detail' => 'No link matches this token.',
            ], 404);
        }

        // Optional gate authorization
        $ability = config('sharelink.management.gate');
        if (is_string($ability) && $ability !== '') {
            if (Gate::denies($ability, $model)) {
                return ResponseFacade::json([
                    'status' => 403,
                    'code' => 'sharelink.forbidden',
                    'title' => 'Forbidden',
                    'detail' => 'You are not authorized to manage this link.',
                ], 403);
            }
        }

        $manager->extend($model, $hours);

        return (new ShareLinkResource($model->fresh()))->response();
    }
}
