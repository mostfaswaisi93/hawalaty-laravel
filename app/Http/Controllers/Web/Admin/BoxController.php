<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Box\StoreBoxRequest;
use App\Http\Requests\Admin\Box\UpdateBoxRequest;
use App\Models\Box;
use App\Services\Admin\BoxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BoxController extends Controller
{
    public function __construct(private readonly BoxService $service) {}

    public function index(): View
    {
        return view('admin.boxes.index', [
            'boxes' => $this->service->list(),
        ]);
    }

    public function store(StoreBoxRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('admin.boxes.index')->with('success', __('تمت إضافة الصندوق بنجاح.'));
    }

    public function update(UpdateBoxRequest $request, string $box): RedirectResponse
    {
        $this->service->update(Box::findOrFail($box), $request->validated());

        return redirect()->route('admin.boxes.index')->with('success', __('تم تحديث الصندوق بنجاح.'));
    }

    public function destroy(string $box): RedirectResponse
    {
        $this->service->delete(Box::findOrFail($box));

        return redirect()->route('admin.boxes.index')->with('success', __('تم حذف الصندوق.'));
    }
}
