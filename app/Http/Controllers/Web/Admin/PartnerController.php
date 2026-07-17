<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\HasPerPage;
use App\Http\Requests\Admin\Partner\StorePartnerRequest;
use App\Http\Requests\Admin\Partner\UpdatePartnerRequest;
use App\Models\Partner;
use App\Services\Admin\PartnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly PartnerService $service) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);

        return view('admin.partners.index', [
            'partners' => Partner::query()->orderBy('name')->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
        ]);
    }

    public function store(StorePartnerRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('admin.partners.index')->with('success', __('تمت إضافة الشريك بنجاح.'));
    }

    public function update(UpdatePartnerRequest $request, string $partner): RedirectResponse
    {
        $this->service->update(Partner::findOrFail($partner), $request->validated());

        return redirect()->route('admin.partners.index')->with('success', __('تم تحديث الشريك بنجاح.'));
    }

    public function destroy(string $partner): RedirectResponse
    {
        $this->service->delete(Partner::findOrFail($partner));

        return redirect()->route('admin.partners.index')->with('success', __('تم حذف الشريك.'));
    }
}
