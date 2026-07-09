<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Tenant\Bank to match the Angular `Bank` interface in
 * D:\Angular\Hawalaty\src\app\data\banks.ts exactly.
 *
 * @mixin \App\Models\Tenant\Bank
 */
class BankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'code' => $this->code,
            'active' => $this->active,
            'phone' => $this->phone,
        ];
    }
}
