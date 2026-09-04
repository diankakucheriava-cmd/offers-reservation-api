<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OfferUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function store(StoreReservationRequest $request, Offer $offer): JsonResponse
    {
        try {
            $reservation = $offer->reserve($request->validated());
        } catch (OfferUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }
}
