<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Expense\StoreExpenseRequest;
use App\Http\Requests\Tenant\Expense\UpdateExpenseRequest;
use App\Models\Tenant\Expense;
use App\Services\Tenant\BankService;
use App\Services\Tenant\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $service,
        private readonly BankService $banks,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المصروفات.'));
        }

        $search = $request->query('search');

        return view('expenses.index', [
            'expenses' => $this->service->list($search),
            'banks' => $this->banks->list(),
            'search' => $search,
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->create($request->validated());

        return redirect()->route('expenses.index')->with('success', __('تم إضافة المصروف بنجاح.'));
    }

    public function update(UpdateExpenseRequest $request, string $expense): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->update(Expense::findOrFail($expense), $request->validated());

        return redirect()->route('expenses.index')->with('success', __('تم تحديث المصروف بنجاح.'));
    }

    public function destroy(string $expense): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->delete(Expense::findOrFail($expense));

        return redirect()->route('expenses.index')->with('success', __('تم حذف المصروف.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::ExpensesManage);
    }
}
