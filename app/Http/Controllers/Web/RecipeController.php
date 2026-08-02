<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\RecipeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class RecipeController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly RecipeService $recipes,
        private readonly CatalogNaming $naming,
    ) {}

    /**
     * Items that have a recipe, with what they cost to make against what they
     * sell for - the menu-engineering view.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        $perPage = $this->perPage($request, 20);
        $search = $request->query('search');

        $items = MenuItem::query()
            ->where('kind', 'goods')
            ->has('recipeComponents')
            ->with(['menuCategory', 'recipeComponents.component'])
            ->whereSearch(['name', 'sku'], $search)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('recipes.index', [
            'items' => $items,
            'costs' => $items->mapWithKeys(fn (MenuItem $item) => [
                $item->id => [
                    'cost' => $this->recipes->theoreticalCost($item),
                    'margin' => $this->recipes->theoreticalMargin($item),
                ],
            ]),
            'candidates' => MenuItem::query()
                ->where('kind', 'goods')
                ->doesntHave('recipeComponents')
                ->orderBy('name')
                ->get(),
            'naming' => $this->naming,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function edit(string $item): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        $model = MenuItem::with('recipeComponents.component')->findOrFail($item);

        return view('recipes.edit', [
            'item' => $model,
            'candidates' => $this->recipes->eligibleComponents($model),
            'exploded' => $this->recipes->explode($model),
            'theoreticalCost' => $this->recipes->theoreticalCost($model),
            'margin' => $this->recipes->theoreticalMargin($model),
            'naming' => $this->naming,
        ]);
    }

    public function update(Request $request, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'components' => ['nullable', 'array'],
            'components.*.componentId' => ['required', 'string', 'exists:tenant.menu_items,id'],
            'components.*.quantity' => ['required', 'numeric', 'gt:0'],
            'components.*.wastePercent' => ['nullable', 'numeric', 'min:0', 'max:99'],
        ]);

        try {
            $this->recipes->replace(MenuItem::findOrFail($item), $data['components'] ?? []);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('recipes.edit', $item)->with('success', __('تم حفظ الوصفة.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::MenuManage);
    }
}
