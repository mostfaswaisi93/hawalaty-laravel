<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settlement\StoreSettlementRequest;
use App\Models\Settlement;
use App\Services\Admin\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

/**
 * Partner clearing (المقاصة) - the accounting screen that answers "what does
 * each partner still have coming, after what they already took?".
 *
 * The service owns every rule that could produce a wrong number (period
 * boundaries, rounding residual, one-draft-at-a-time); this controller only
 * translates its exceptions into flash messages.
 */
class SettlementController extends Controller
{
    public function __construct(private readonly SettlementService $service) {}

    public function index(): View
    {
        return view('admin.settlements.index', [
            'preview' => $this->service->preview(),
            'settlements' => $this->service->list(),
        ]);
    }

    public function show(Settlement $settlement): View
    {
        return view('admin.settlements.show', [
            'settlement' => $settlement->load(['lines', 'admin']),
        ]);
    }

    public function store(StoreSettlementRequest $request): RedirectResponse
    {
        try {
            $settlement = $this->service->create(
                $request->validated(),
                auth('admin')->id(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', $settlement->isConfirmed()
                ? __('تمت المقاصة وإقفال الفترة بنجاح.')
                : __('تم حفظ المقاصة كمسودّة.'));
    }

    public function confirm(Settlement $settlement): RedirectResponse
    {
        try {
            $this->service->confirm($settlement);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', __('تم تأكيد المقاصة وإقفال الفترة.'));
    }

    public function destroy(Settlement $settlement): RedirectResponse
    {
        try {
            $this->service->deleteDraft($settlement);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.settlements.index')
            ->with('success', __('تم حذف المسودّة.'));
    }
}
