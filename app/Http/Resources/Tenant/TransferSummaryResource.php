<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One order as a REPORT ROW - the four columns a period summary actually reads.
 *
 * Exists because ReportService narrows its select on purpose:
 *
 *     ->select(['id', 'customer', 'status', 'amount', 'created_at'])
 *
 * which is the right call for a list that can run to a whole month. The API
 * then wrapped those partial models in TransferResource, which reads
 * `payment_method->value`, the bank relation, the sender fields - none of them
 * loaded. So /api/v1/reports returned 500 the moment the range held a single
 * order, and nothing caught it because no test had ever called the endpoint.
 *
 * A resource shaped like the projection is the honest fix: the alternative is
 * loading every column of every order in a range to fill fields a report does
 * not show.
 *
 * @mixin Transfer
 */
class TransferSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'customer' => $this->customer,
            'date' => $this->created_at?->toDateString(),
            'status' => $this->status?->value,
            'amount' => (float) $this->amount,
        ];
    }
}
