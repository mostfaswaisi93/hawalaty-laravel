<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
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
    use HasPerPage;

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
        $perPage = $this->perPage($request, 20);

        return view('expenses.index', [
            'expenses' => $this->service->list($search, $perPage),
            // Unpaginated on purpose: this feeds the "bank" select in the
            // expense form, where a page of 20 would hide the rest.
            'banks' => $this->banks->list(),
            'search' => $search,
            'perPage' => $perPage,
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
