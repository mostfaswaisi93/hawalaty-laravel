<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Requests\Admin\Box\StoreBoxRequest;
use App\Http\Requests\Admin\Box\UpdateBoxRequest;
use App\Models\Box;
use App\Services\Admin\BoxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoxController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly BoxService $service) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);

        return view('admin.boxes.index', [
            'boxes' => Box::query()->orderBy('box_name')->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
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
