<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payment\StorePaymentRequest;
use App\Models\Box;
use App\Models\License;
use App\Services\Admin\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service) {}

    public function index(): View
    {
        return view('admin.payments.index', [
            'payments' => $this->service->list(),
            'boxes' => Box::query()->orderBy('box_name')->get(),
            'licenses' => License::query()->orderBy('customer_name')->get(),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('admin.payments.index')
            ->with('success', __('تم تسجيل الدفعة وتجديد الترخيص بنجاح.'));
    }
}
