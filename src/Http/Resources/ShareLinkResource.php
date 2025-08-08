<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Http\Resources;

use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ShareLink $resource
 */
class ShareLinkResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $model = $this->resource;

        return [
            'token' => $model->token,
            'resource' => $model->resource,
            'metadata' => $model->metadata,
            'clicks' => $model->click_count,
        ];
    }
}
