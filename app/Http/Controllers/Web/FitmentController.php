<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\VehicleMake;
use App\Models\Tenant\VehicleModel;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\FitmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class FitmentController extends Controller
{
    public function __construct(
        private readonly FitmentService $fitment,
        private readonly CatalogNaming $naming,
    ) {}

    /**
     * The parts finder - the screen the counter lives on.
     *
     * Three ways in: pick the vehicle, paste a chassis number, or type whatever
     * is stamped on the old part.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canView()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لعرض القائمة.'));
        }

        $modelId = $request->query('modelId');
        $year = filled($request->query('year')) ? (int) $request->query('year') : null;
        $engine = $request->query('engine');
        $reference = (string) $request->query('reference', '');
        $vin = (string) $request->query('vin', '');

        $decoded = $vin !== '' ? $this->fitment->decodeVin($vin) : null;

        // A pasted chassis number narrows the pickers rather than replacing
        // them: it can name the make and the year, never the model.
        if ($decoded && $decoded['year'] !== null && $year === null) {
            $year = $decoded['year'];
        }

        return view('fitment.index', [
            'makes' => VehicleMake::query()->active()->with(['models' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'modelId' => $modelId,
            'model' => filled($modelId) ? VehicleModel::with('make')->find($modelId) : null,
            'year' => $year,
            'engine' => $engine,
            'parts' => $this->fitment->partsFor($modelId, $year, $engine),
            'reference' => $reference,
            'matches' => $reference !== '' ? $this->fitment->findByReference($reference) : collect(),
            'vin' => $vin,
            'decoded' => $decoded,
            'naming' => $this->naming,
        ]);
    }

    /**
     * Vehicles the shop serves. Has to exist before anything can fit one.
     */
    public function vehicles(): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        return view('fitment.vehicles', [
            'makes' => $this->fitment->makes(),
            'naming' => $this->naming,
        ]);
    }

    public function storeMake(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('tenant.vehicle_makes', 'name')],
            // Three characters, and never I, O or Q - ISO 3779 excludes them so
            // they cannot be read as 1 and 0.
            'wmi' => ['nullable', 'string', 'size:3', 'regex:/^[A-HJ-NPR-Z0-9]{3}$/i'],
        ], [
            'wmi.regex' => __('رمز الصانع WMI: ثلاثة رموز بدون الحروف I و O و Q.'),
        ]);

        $this->fitment->createMake($data);

        return redirect()->route('fitment.vehicles')->with('success', __('تمت إضافة الماركة.'));
    }

    public function storeModel(Request $request, string $make): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $model = VehicleMake::findOrFail($make);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('tenant.vehicle_models', 'name')->where('vehicle_make_id', $model->id),
            ],
        ]);

        $this->fitment->createModel($model, $data);

        return redirect()->route('fitment.vehicles')->with('success', __('تمت إضافة الموديل.'));
    }

    public function destroyModel(string $model): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->fitment->deleteModel(VehicleModel::findOrFail($model));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('fitment.vehicles')->with('success', __('تم حذف الموديل.'));
    }

    /**
     * Everything one part says about itself: what it fits, what else it is
     * called, and what replaced it.
     */
    public function edit(string $item): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        $part = MenuItem::with(['fitments.vehicleModel.make', 'references', 'supersededBy'])->findOrFail($item);

        return view('fitment.edit', [
            'item' => $part,
            'makes' => VehicleMake::query()->active()->with(['models' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'chain' => $this->fitment->supersessionChain($part),
            'candidates' => MenuItem::query()
                ->where('kind', 'goods')
                ->where('id', '!=', $part->id)
                ->orderBy('name')
                ->get(),
            'naming' => $this->naming,
        ]);
    }

    public function update(Request $request, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'fitments' => ['nullable', 'array'],
            'fitments.*.vehicleModelId' => ['required', 'string', 'exists:tenant.vehicle_models,id'],
            'fitments.*.yearFrom' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'fitments.*.yearTo' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'fitments.*.engine' => ['nullable', 'string', 'max:64'],
            'fitments.*.notes' => ['nullable', 'string', 'max:255'],

            'references' => ['nullable', 'array'],
            'references.*.reference' => ['required', 'string', 'max:64'],
            'references.*.type' => ['nullable', Rule::in(['oe', 'aftermarket'])],
            'references.*.brand' => ['nullable', 'string', 'max:255'],

            'supersededByItemId' => ['nullable', 'string', 'exists:tenant.menu_items,id'],
            'coreCharge' => ['nullable', 'numeric', 'min:0'],
        ]);

        $part = MenuItem::findOrFail($item);

        try {
            $this->fitment->replaceFitments($part, $data['fitments'] ?? []);
            $this->fitment->replaceReferences($part, $data['references'] ?? []);
            $this->fitment->supersede($part, $data['supersededByItemId'] ?? null);

            $part->update(['core_charge' => $data['coreCharge'] ?? 0]);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('fitment.edit', $item)->with('success', __('تم حفظ بيانات الملاءمة.'));
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
