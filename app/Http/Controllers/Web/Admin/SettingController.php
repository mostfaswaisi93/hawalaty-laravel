<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\HasPerPage;
use App\Models\Setting;
use App\Services\Admin\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly SettingService $service) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);

        return view('admin.settings.index', [
            'settings' => Setting::query()->orderBy('key')->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
        ]);
    }

    /**
     * Handles both "add" and "edit" - Setting::updateOrCreate keys on the
     * `key`, so posting an existing key updates its value and a new key
     * inserts a row.
     */
    public function upsert(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
        ]);

        $this->service->upsert($data['key'], $data['value'] ?? null);

        return redirect()->route('admin.settings.index')->with('success', __('تم حفظ الإعداد.'));
    }
}
