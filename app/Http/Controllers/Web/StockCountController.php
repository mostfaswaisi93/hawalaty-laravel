<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\Location;
use App\Models\Tenant\StockCount;
use App\Services\Tenant\StockCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class StockCountController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly StockCountService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        $perPage = $this->perPage($request, 20);

        return view('stock-counts.index', [
            'counts' => StockCount::query()
                ->with(['location', 'teamMember'])
                ->withCount('lines')
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'locationId' => ['required', 'string', 'exists:tenant.locations,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $count = $this->service->open($data['locationId'], $data['reference'] ?? null, $data['notes'] ?? null);

        return redirect()->route('stock-counts.show', $count->id)
            ->with('success', __('تم فتح جلسة جرد. أدخل الكميات الفعلية ثم رحّلها.'));
    }

    public function show(string $stockCount): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        return view('stock-counts.show', [
            'count' => StockCount::with(['lines.item', 'location', 'teamMember'])->findOrFail($stockCount),
        ]);
    }

    public function update(Request $request, string $stockCount): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'counted' => ['array'],
            'counted.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->service->record(
                StockCount::with('lines')->findOrFail($stockCount),
                $data['counted'] ?? [],
            );
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('تم حفظ الكميات المجرودة.'));
    }

    public function post(string $stockCount): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->post(StockCount::with('lines')->findOrFail($stockCount));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-counts.show', $stockCount)
            ->with('success', __('تم ترحيل فروقات الجرد إلى المخزون.'));
    }

    public function cancel(string $stockCount): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->cancel(StockCount::findOrFail($stockCount));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-counts.index')->with('success', __('تم إلغاء جلسة الجرد.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::InventoryManage);
    }
}
