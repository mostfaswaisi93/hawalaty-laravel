<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'series' => $this->series,
            'sequence' => $this->sequence,

            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),

            // UNCL 1001, the code that goes on the wire.
            'documentTypeCode' => $this->type->documentTypeCode(),

            'issuedAt' => $this->issued_at?->toIso8601String(),
            'currency' => $this->currency_code,

            'seller' => [
                'name' => $this->seller_name,
                'taxNumber' => $this->seller_tax_number,
                'address' => $this->seller_address,
            ],
            'buyer' => [
                'name' => $this->buyer_name,
                'taxNumber' => $this->buyer_tax_number,
                'address' => $this->buyer_address,
            ],

            'totals' => [
                'lineExtension' => (float) $this->line_extension_amount,
                'allowance' => (float) $this->allowance_total,
                'taxExclusive' => (float) $this->tax_exclusive_amount,
                'tax' => (float) $this->tax_amount,
                'taxInclusive' => (float) $this->tax_inclusive_amount,
                'payable' => (float) $this->payable_amount,
                'balances' => $this->totalsBalance(),
            ],

            'taxBreakdown' => $this->whenLoaded('lines', fn () => $this->taxBreakdown()),

            'chain' => [
                'previousHash' => $this->previous_hash,
                'hash' => $this->hash,
            ],

            // The payload, not an image. Rendering it needs a QR library this
            // application does not carry.
            'qrPayload' => $this->qr_payload,

            'transferId' => $this->transfer_id,
            'saleReturnId' => $this->sale_return_id,

            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'position' => $line->position,
                'name' => $line->name,
                'quantity' => (float) $line->quantity,
                'unitCode' => $line->unit_code,
                'unitPrice' => (float) $line->unit_price,
                'lineExtensionAmount' => (float) $line->line_extension_amount,
                'taxCategory' => $line->tax_category,
                'taxRate' => (float) $line->tax_rate,
                'taxAmount' => (float) $line->tax_amount,
            ])),
        ];
    }
}
