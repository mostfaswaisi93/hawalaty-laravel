<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\HasPerPage;
use App\Models\Package;
use App\Services\Admin\PackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `features` is stored as a JSON array. The web form collects it as a
 * newline-separated textarea, so validation happens inline here (splitting the
 * textarea into an array) rather than reusing the API's StorePackageRequest,
 * which expects `features` to already be an array.
 */
class PackageController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly PackageService $service) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);

        return view('admin.packages.index', [
            'packages' => Package::query()->orderBy('price')->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $this->service->create($data);

        return redirect()->route('admin.packages.index')->with('success', __('تمت إضافة الباقة بنجاح.'));
    }

    public function update(Request $request, string $package): RedirectResponse
    {
        $data = $this->validated($request);

        $this->service->update(Package::findOrFail($package), $data);

        return redirect()->route('admin.packages.index')->with('success', __('تم تحديث الباقة بنجاح.'));
    }

    public function destroy(string $package): RedirectResponse
    {
        $this->service->delete(Package::findOrFail($package));

        return redirect()->route('admin.packages.index')->with('success', __('تم حذف الباقة.'));
    }

    /**
     * @return array{name: string, price: float, deviceLimit: int, features: array<int, string>}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'deviceLimit' => ['required', 'integer', 'min:0'],
            'features' => ['nullable', 'string'],
        ]);

        $validated['features'] = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['features'] ?? '')))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();

        return $validated;
    }
}
