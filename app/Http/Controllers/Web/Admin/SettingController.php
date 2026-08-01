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

            'registry' => $this->registry,
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->settings->put($request->validated()['settings']);

        return redirect()
            ->route('admin.settings.index')
            ->with('success', __('تم حفظ الإعدادات.'));
    }

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
