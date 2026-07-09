<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Expense\StoreExpenseRequest;
use App\Http\Requests\Tenant\Expense\UpdateExpenseRequest;
use App\Http\Resources\Tenant\ExpenseResource;
use App\Models\Tenant\Expense;
use App\Services\Tenant\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ExpenseResource::collection($this->service->list($request->query('search')));
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $expense = $this->service->create($request->validated());

        return (new ExpenseResource($expense))->response()->setStatusCode(201);
    }

    public function update(UpdateExpenseRequest $request, string $expense): JsonResponse
    {
        $expenseModel = Expense::findOrFail($expense);

        $this->authorize('update', $expenseModel);

        $expenseModel = $this->service->update($expenseModel, $request->validated());

        return (new ExpenseResource($expenseModel))->response();
    }

    public function destroy(string $expense): JsonResponse
    {
        $expenseModel = Expense::findOrFail($expense);

        $this->authorize('delete', $expenseModel);

        $this->service->delete($expenseModel);

        return response()->json(null, 204);
    }
}
