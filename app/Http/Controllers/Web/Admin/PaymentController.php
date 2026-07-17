<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\HasPerPage;
use App\Http\Requests\Admin\Payment\StorePaymentRequest;
use App\Models\Box;
use App\Models\License;
use App\Services\Admin\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly PaymentService $service) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);

        return view('admin.payments.index', [
            'payments' => $this->service->list($perPage),
            'boxes' => Box::query()->orderBy('box_name')->get(),
            'licenses' => License::query()->orderBy('customer_name')->get(),
            'perPage' => $perPage,
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('admin.payments.index')
            ->with('success', __('تم تسجيل الدفعة وتجديد الترخيص بنجاح.'));
    }
}
