<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportResource;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class ImportController extends Controller
{
    public function store(StoreImportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $supplier = Supplier::where('code', $data['supplier'])->firstOrFail();

        $import = Import::createOrFirstPending(
            $supplier,
            $data['external_import_id'],
            $data['sent_at'],
            count($data['offers'])
        );

        if ($import->wasRecentlyCreated) {
            ProcessImportJob::dispatch($import, $data['offers']);
        }

        return (new ImportResource($import))
            ->response()
            ->setStatusCode(202);
    }

    public function show(Import $import): ImportResource
    {
        return new ImportResource($import->load('supplier'));
    }
}
