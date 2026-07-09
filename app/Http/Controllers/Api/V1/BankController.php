<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Bank\StoreBankRequest;
use App\Http\Requests\Tenant\Bank\UpdateBankRequest;
use App\Http\Resources\Tenant\BankResource;
use App\Models\Tenant\Bank;
use App\Services\Tenant\BankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BankController extends Controller
{
    public function __construct(private readonly BankService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return BankResource::collection($this->service->list($request->query('search')));
    }

    public function store(StoreBankRequest $request): JsonResponse
    {
        $this->authorize('create', Bank::class);

        $bank = $this->service->create($request->validated());

        return (new BankResource($bank))->response()->setStatusCode(201);
    }

    public function update(UpdateBankRequest $request, string $bank): JsonResponse
    {
        $bankModel = Bank::findOrFail($bank);

        $this->authorize('update', $bankModel);

        $bankModel = $this->service->update($bankModel, $request->validated());

        return (new BankResource($bankModel))->response();
    }

    public function destroy(string $bank): JsonResponse
    {
        $bankModel = Bank::findOrFail($bank);

        $this->authorize('delete', $bankModel);

        $this->service->delete($bankModel);

        return response()->json(null, 204);
    }
}
