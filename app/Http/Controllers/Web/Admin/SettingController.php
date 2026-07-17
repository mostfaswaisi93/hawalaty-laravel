<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $service) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => $this->service->list(),
        ]);
    }

    /**
     * Handles both "add" and "edit" — Setting::updateOrCreate keys on the
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
