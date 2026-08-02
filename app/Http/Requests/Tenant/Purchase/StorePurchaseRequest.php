<?php

namespace App\Http\Requests\Tenant\Purchase;

use App\Enums\BusinessCapability;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\BusinessProfile;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tracksBatches = app(BusinessProfile::class)->has(BusinessCapability::Batches);

        return [
            'supplierId' => ['nullable', 'string', 'exists:tenant.suppliers,id'],
            'locationId' => ['required', 'string', 'exists:tenant.locations,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'invoiceNumber' => ['nullable', 'string', 'max:64'],
            'orderedAt' => ['nullable', 'date'],
            'otherCosts' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.itemId' => ['required', 'string', 'exists:tenant.menu_items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unitCost' => ['required', 'numeric', 'min:0'],
            'lines.*.taxRate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Only demanded where the trade demands it. A bookshop typing a lot
            // number on every line would abandon the feature within a week.
            'lines.*.batchNumber' => [$tracksBatches ? 'required' : 'nullable', 'string', 'max:64'],
            'lines.*.expiresAt' => [$tracksBatches ? 'required' : 'nullable', 'date', 'after:today'],

            // One per line, as they come off a delivery note or a scanner.
            'lines.*.serials' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * A serialised item needs exactly as many numbers as units.
     *
     * Checked here rather than at receipt so the storekeeper finds out while the
     * boxes are still in front of them, not a day later.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lines = $this->input('lines', []);

            if (! is_array($lines)) {
                return;
            }

            $items = MenuItem::query()
                ->whereIn('id', array_filter(array_column($lines, 'itemId')))
                ->where('track_serials', true)
                ->pluck('name', 'id');

            foreach ($lines as $index => $line) {
                $name = $items[$line['itemId'] ?? ''] ?? null;

                if ($name === null) {
                    continue;
                }

                $count = collect(preg_split('/[\r\n,]+/', (string) ($line['serials'] ?? '')))
                    ->map(fn ($serial) => trim((string) $serial))
                    ->filter()
                    ->unique()
                    ->count();

                if ($count !== (int) ($line['quantity'] ?? 0)) {
                    $validator->errors()->add("lines.{$index}.serials", __(
                        'أدخل :quantity رقمًا تسلسليًا لـ :item — أُدخل :count.',
                        ['quantity' => (int) ($line['quantity'] ?? 0), 'item' => $name, 'count' => $count],
                    ));
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'lines.required' => __('أضف صنفًا واحدًا على الأقل لأمر الشراء.'),
            'lines.*.batchNumber.required' => __('رقم اللوط مطلوب لكل صنف في هذا النشاط.'),
            'lines.*.expiresAt.required' => __('تاريخ الصلاحية مطلوب لكل صنف في هذا النشاط.'),
            'lines.*.expiresAt.after' => __('لا يمكن استلام بضاعة منتهية أو تنتهي اليوم.'),
        ];
    }
}
