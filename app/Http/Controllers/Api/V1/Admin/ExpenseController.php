<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Expense\StoreExpenseRequest;
use App\Http\Resources\Admin\ExpenseResource;
use App\Services\Admin\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ExpenseResource::collection($this->service->list((int) $request->query('per_page', 20)));
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->service->create($request->validated());

        return (new ExpenseResource($expense))->response()->setStatusCode(201);
    }
}
