<?php

namespace App\Http\Controllers\Web\Admin;

use App\Exceptions\InsufficientBoxBalanceException;
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

    /**
     * The insufficient-balance catch is not belt-and-braces: the services check
     * the balance INSIDE their locked transaction, which is the only place the
     * answer is authoritative, so a refusal can arrive here even when the
     * request-level rule passed. Rendering it as an `amount` error puts it on
     * the field the admin has to change, and withInput() reopens the modal with
     * their figures still in it.
     */
    public function storeWithdrawal(StoreWithdrawalRequest $request, WithdrawalService $service): RedirectResponse
    {
        try {
            $service->create($request->validated());
        } catch (InsufficientBoxBalanceException $e) {
            return back()->withInput()->withErrors(['amount' => $e->userMessage()]);
        }

        return redirect()->route('admin.finance')->with('success', __('تم تسجيل السحب وخصمه من رصيد الصندوق.'));
    }

    public function storeExpense(StoreExpenseRequest $request, ExpenseService $service): RedirectResponse
    {
        try {
            $service->create($request->validated());
        } catch (InsufficientBoxBalanceException $e) {
            return back()->withInput()->withErrors(['amount' => $e->userMessage()]);
        }

        return redirect()->route('admin.finance')->with('success', __('تم تسجيل المصروف وخصمه من رصيد الصندوق.'));
    }
}
