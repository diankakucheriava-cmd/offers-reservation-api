<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PropertyCollection extends ResourceCollection
{
    public $collects = PropertyResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'next' => $this->resource->nextPageUrl(),
            'prev' => $this->resource->previousPageUrl(),
            'per_page' => $this->resource->perPage(),
        ];
    }

    // bypass Laravel's automatic pagination envelope (links/meta) so our own shape is returned as-is
    public function toResponse($request): JsonResponse
    {
        return response()->json($this->toArray($request));
    }
}
