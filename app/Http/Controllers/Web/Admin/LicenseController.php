<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Requests\Admin\License\ChangePackageRequest;
use App\Http\Requests\Admin\License\StoreLicenseRequest;
use App\Models\License;
use App\Models\Package;
use App\Services\Admin\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class LicenseController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly LicenseService $service) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);

        return view('admin.licenses.index', [
            'licenses' => $this->service->list(
                $request->query('search'),
                $request->query('status'),
                $request->query('packageId'),
                $perPage,
            ),
            'packages' => Package::query()->orderBy('price')->get(),
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'packageId' => $request->query('packageId'),
            'perPage' => $perPage,
        ]);
    }

    public function show(string $license): View
    {
        $data = $this->service->show(License::findOrFail($license));

        $data['packages'] = Package::query()->orderBy('price')->get();

        return view('admin.licenses.show', $data);
    }

    public function store(StoreLicenseRequest $request): RedirectResponse
    {
        try {
            $license = $this->service->create($request->validated(), auth('admin')->user());
        } catch (Throwable $e) {
            return redirect()->route('admin.licenses.index')
                ->with('error', __('تعذّر إنشاء الترخيص: ').$e->getMessage());
        }

        return redirect()->route('admin.licenses.index')
            ->with('success', __('تم إنشاء الترخيص :code وقاعدة بياناته بنجاح.', ['code' => $license->license_code]));
    }

    public function suspend(string $license): RedirectResponse
    {
        $this->service->suspend(License::findOrFail($license), auth('admin')->user());

        return redirect()->back()->with('success', __('تم تعليق الترخيص.'));
    }

    public function activate(string $license): RedirectResponse
    {
        $this->service->activate(License::findOrFail($license), auth('admin')->user());

        return redirect()->back()->with('success', __('تم تفعيل الترخيص.'));
    }

    public function migrate(string $license): RedirectResponse
    {
        try {
            $this->service->migrate(License::findOrFail($license), auth('admin')->user());
        } catch (Throwable $e) {
            return redirect()->back()->with('error', __('تعذّر تشغيل الترحيلات: ').$e->getMessage());
        }

        return redirect()->back()->with('success', __('تم تشغيل ترحيلات قاعدة البيانات بنجاح.'));
    }

    public function changePackage(ChangePackageRequest $request, string $license): RedirectResponse
    {
        $this->service->changePackage(
            License::findOrFail($license),
            $request->validated()['packageId'],
            auth('admin')->user(),
        );

        return redirect()->back()->with('success', __('تم تغيير باقة الترخيص.'));
    }
}
