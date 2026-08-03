<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item as a client sees it.
 *
 * This is the whole of what an offline till knows about a product, so it has to
 * carry the facts the till must act on without asking: the unit it is sold in,
 * whether the price already includes tax, whether a unit cannot leave without a
 * serial, whether it needs a prescription. A client that has to come back online
 * to find out it may not sell something is not an offline client.
 *
 * The trade-specific groups are omitted rather than sent as nulls where the
 * vertical has no use for them - a payload saying `prescription: null` to a
 * hardware shop invites a client to render a field that means nothing there.
 */
class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge([
            'id' => (string) $this->id,
            'name' => $this->name,
            'category' => $this->menuCategory?->name,
            'categoryId' => $this->menu_category_id,
            'icon' => $this->icon,
            'kind' => $this->kind,
            'price' => (float) $this->price,
            'active' => (bool) $this->active,

            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'pluCode' => $this->plu_code,

            // UN/CEFACT Rec 20, plus the flag that tells the till whether to ask
            // for a weight or a count.
            'unitCode' => $this->unit_code,
            'fractional' => ! $this->unit()->isDiscrete(),

            'tax' => [
                'rate' => (float) $this->tax_rate,
                'category' => $this->tax_category,
                'included' => (bool) $this->tax_included,
            ],

            'trackStock' => (bool) $this->track_stock,
            'trackSerials' => (bool) $this->track_serials,

            // A dish is assembled at the till and never stocked as itself, so a
            // client showing its balance would be showing a number that is
            // always zero.
            'assembledToOrder' => $this->assemblesToOrder(),

            'isVariant' => $this->isVariant(),
            'parentId' => $this->parent_item_id,
        ],
            $this->warranty_months ? ['warrantyMonths' => (int) $this->warranty_months] : [],
            $this->service_minutes ? ['serviceMinutes' => (int) $this->service_minutes] : [],

            $this->is_prescription || filled($this->control_schedule) ? [
                'prescription' => [
                    'required' => (bool) $this->is_prescription,

                    // Filled means controlled, and controlled means the sale is
                    // refused without the patient's identity.
                    'controlSchedule' => $this->control_schedule,
                    'coldChain' => (bool) $this->cold_chain,
                ],
            ] : [],
        );
    }
}
