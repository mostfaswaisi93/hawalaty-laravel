<?php

namespace App\Http\Resources\Admin;

use App\Models\Settlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Settlement
 */
class SettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'periodFrom' => $this->period_from?->toDateString(),
            'periodTo' => $this->period_to?->toDateString(),

            'totalPayments' => (float) $this->total_payments,
            'totalExpenses' => (float) $this->total_expenses,
            // payments - expenses. Withdrawals are distributions OF this, never
            // subtracted from it - see SettlementService.
            'distributableProfit' => (float) $this->distributable_profit,
            'totalEntitlement' => (float) $this->total_entitlement,
            'totalDrawn' => (float) $this->total_drawn,
            'totalNet' => (float) $this->total_net,
            'retained' => (float) $this->retained,

            'status' => $this->status->value,
            'notes' => $this->notes,
            'confirmedAt' => $this->confirmed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),

            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'partnerId' => $line->partner_id,
                // Snapshots taken when the run was created; a later change to
                // the partner never rewrites a confirmed statement.
                'partnerName' => $line->partner_name,
                'percentage' => (float) $line->percentage,
                'entitlement' => (float) $line->entitlement,
                'drawn' => (float) $line->drawn,
                'net' => (float) $line->net,
            ])),
        ];
    }
}
