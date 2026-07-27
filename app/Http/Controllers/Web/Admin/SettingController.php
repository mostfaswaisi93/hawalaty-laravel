<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Setting\UpdateSettingsRequest;
use App\Services\Admin\SettingService;
use App\Settings\PlatformSettings;
use App\Settings\SettingRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform settings.
 *
 * The page is a grouped FORM over the registered settings rather than a
 * paginated key/value table - an admin should not have to know that a key
 * called `trial_days` exists, nor type it by hand.
 *
 * The free-form key/value endpoint is kept (`upsert`) because the settings
 * table is still a plain key/value store and the API exposes it as one; it now
 * backs the "custom keys" section for anything outside the registry.
 */
class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $service,
        private readonly SettingRegistry $registry,
        private readonly PlatformSettings $settings,
    ) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'groups' => $this->registry->groups(),
            'values' => $this->settings->all(),
            'custom' => $this->settings->unregistered(),
        ]);
    }

    /** Bulk-saves the grouped form. */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->settings->put($request->validated()['settings']);

        return redirect()
            ->route('admin.settings.index')
            ->with('success', __('تم حفظ الإعدادات.'));
    }

    /**
     * Free-form key/value upsert, used by the "custom keys" section. Registered
     * keys are rejected here so the typed form stays the only way to set them -
     * otherwise a raw string could bypass that setting's validation.
     */
    public function upsert(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
        ]);

        if ($this->registry->has($data['key'])) {
            return back()->with('error', __('هذا المفتاح له حقل مخصّص في الأعلى، عدّله من هناك.'));
        }

        $this->service->upsert($data['key'], $data['value'] ?? null);
        $this->settings->flush();

        return redirect()->route('admin.settings.index')->with('success', __('تم حفظ الإعداد.'));
    }

    public function destroy(string $key): RedirectResponse
    {
        if ($this->registry->has($key)) {
            return back()->with('error', __('لا يمكن حذف إعداد أساسي في المنصّة.'));
        }

        $this->service->forget($key);
        $this->settings->flush();

        return redirect()->route('admin.settings.index')->with('success', __('تم حذف المفتاح.'));
    }
}
