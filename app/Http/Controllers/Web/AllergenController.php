<?php

namespace App\Http\Controllers\Web;

use App\Enums\Allergen;
use App\Enums\AllergenPresence;
use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\AllergenService;
use App\Services\Tenant\CatalogNaming;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AllergenController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly AllergenService $allergens,
        private readonly CatalogNaming $naming,
    ) {}

    /**
     * The compliance view: what every item is labelled with, and - more to the
     * point - which ones nobody has checked.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        $perPage = $this->perPage($request, 25);
        $search = $request->query('search');
        $filter = $request->query('filter');

        $query = MenuItem::query()
            ->where('kind', 'goods')
            ->with(['menuCategory', 'allergenDeclarations'])
            ->whereSearch(['name', 'sku'], $search)
            ->when($filter === 'unreviewed', fn ($q) => $q->whereNull('allergens_reviewed_at'))
            ->orderBy('name');

        $items = $query->paginate($perPage)->withQueryString();

        return view('allergens.index', [
            'items' => $items,
            'labels' => $this->allergens->labelsFor($items->getCollection()),
            'unreviewedCount' => $this->allergens->unreviewed()->count(),
            'blindDishes' => $this->allergens->restingOnUnreviewedComponents(),
            'spread' => $this->allergens->spread(),
            'allergens' => Allergen::cases(),
            'naming' => $this->naming,
            'search' => $search,
            'filter' => $filter,
            'perPage' => $perPage,
        ]);
    }

    public function edit(string $item): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        $model = MenuItem::with('allergenDeclarations')->findOrFail($item);

        return view('allergens.edit', [
            'item' => $model,
            'declared' => $this->allergens->declared($model),
            'label' => $this->allergens->labelFor($model),
            'allergens' => Allergen::cases(),
            'naming' => $this->naming,
        ]);
    }

    public function update(Request $request, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'declarations' => ['nullable', 'array'],
            'declarations.*' => [Rule::enum(AllergenPresence::class)],
        ]);

        $model = MenuItem::findOrFail($item);

        // Keys arrive from checkbox names, so anything unrecognised is dropped
        // by the service rather than trusted here.
        $this->allergens->declare($model, $data['declarations'] ?? []);

        return redirect()->route('allergens.index')
            ->with('success', __('تم حفظ إفصاح المسبّبات لـ :item.', ['item' => $model->name]));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::MenuManage);
    }
}
