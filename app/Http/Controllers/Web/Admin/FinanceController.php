<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Expense\StoreExpenseRequest;
use App\Http\Requests\Admin\Withdrawal\StoreWithdrawalRequest;
use App\Models\Box;
use App\Models\Partner;
use App\Models\Payment;
use App\Services\Admin\ExpenseService;
use App\Services\Admin\FinanceSummaryService;
use App\Services\Admin\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The platform's internal accounting dashboard - reuses
 * FinanceSummaryService for the read side, and WithdrawalService/ExpenseService
 * for the two "money out of a box" actions that have no dedicated nav page.
 */
class FinanceController extends Controller
{
    public function __construct(private readonly FinanceSummaryService $service) {}

    public function index(): View
    {
        return view('admin.finance.index', [
            'summary' => $this->service->summarize(),
            'boxes' => Box::query()->orderBy('box_name')->get(),
            'partners' => Partner::query()->orderBy('name')->get(),
            'recentPayments' => Payment::with(['box', 'license'])->latest()->take(8)->get(),
        ]);
    }

    public function storeWithdrawal(StoreWithdrawalRequest $request, WithdrawalService $service): RedirectResponse
    {
        $service->create($request->validated());

        return redirect()->route('admin.finance')->with('success', __('تم تسجيل السحب وخصمه من رصيد الصندوق.'));
    }

    public function storeExpense(StoreExpenseRequest $request, ExpenseService $service): RedirectResponse
    {
        $service->create($request->validated());

        return redirect()->route('admin.finance')->with('success', __('تم تسجيل المصروف وخصمه من رصيد الصندوق.'));
    }
}
