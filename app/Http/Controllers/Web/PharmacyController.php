<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\PharmacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly PharmacyService $pharmacy,
        private readonly CatalogNaming $naming,
    ) {}

    /**
     * The register, plus the classification gaps that make it unreliable.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canView()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لعرض السجل.'));
        }

        $perPage = $this->perPage($request, 25);

        return view('pharmacy.index', [
            'records' => $this->pharmacy->register(
                $request->query('from'),
                $request->query('to'),
                $request->query('search'),
                $request->query('scope') !== 'all',
                $perPage,
            ),
            'summary' => $this->pharmacy->summary(),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'search' => $request->query('search'),
            'scope' => $request->query('scope'),
            'perPage' => $perPage,
            'naming' => $this->naming,
        ]);
    }

    /**
     * Classification for one medicine, and what else carries the same
     * ingredient.
     */
    public function edit(string $item): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        $medicine = MenuItem::findOrFail($item);

        return view('pharmacy.edit', [
            'item' => $medicine,
            'substitutes' => $this->pharmacy->substitutesFor($medicine),
            'naming' => $this->naming,
        ]);
    }

    public function update(Request $request, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'isPrescription' => ['nullable', 'boolean'],
            'controlSchedule' => ['nullable', 'string', 'max:16'],
            'atcCode' => ['nullable', 'string', 'max:16'],
            'activeIngredient' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:64'],
            'dosageForm' => ['nullable', 'string', 'max:64'],
            'coldChain' => ['nullable', 'boolean'],
        ]);

        MenuItem::findOrFail($item)->update([
            'is_prescription' => (bool) ($data['isPrescription'] ?? false),
            'control_schedule' => filled($data['controlSchedule'] ?? null) ? $data['controlSchedule'] : null,
            'atc_code' => filled($data['atcCode'] ?? null) ? strtoupper($data['atcCode']) : null,
            'active_ingredient' => filled($data['activeIngredient'] ?? null) ? trim($data['activeIngredient']) : null,
            'strength' => $data['strength'] ?? null,
            'dosage_form' => $data['dosageForm'] ?? null,
            'cold_chain' => (bool) ($data['coldChain'] ?? false),
        ]);

        return redirect()->route('pharmacy.edit', $item)->with('success', __('تم حفظ تصنيف الدواء.'));
    }

    /**
     * Search by brand, by generic name, or by ATC code - a counter arrives at
     * the same medicine from all three.
     */
    public function search(Request $request): View|RedirectResponse
    {
        if (! $this->canView()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لعرض السجل.'));
        }

        $term = (string) $request->query('term', '');

        return view('pharmacy.search', [
            'term' => $term,
            'results' => $this->pharmacy->search($term),
            'naming' => $this->naming,
        ]);
    }

    private function canView(): bool
    {
        return auth('tenant')->check();
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::MenuManage);
    }
}
