<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchPropertiesRequest;
use App\Http\Resources\PropertyCollection;
use App\Models\Property;

class PropertyController extends Controller
{
    public function index(SearchPropertiesRequest $request): PropertyCollection
    {
        $data = $request->validated();

        $paginator = Property::withBestOffer(
            $data['check_in'],
            $data['check_out'],
            $data['guests'],
            $data['city'] ?? null
        )
            ->paginate($data['per_page'] ?? 15)
            ->withQueryString();

        return new PropertyCollection($paginator);
    }
}
