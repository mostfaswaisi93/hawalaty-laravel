<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'licenseCode' => $this->license_code,
            'customerName' => $this->customer_name,
            'packageId' => (string) $this->package_id,
            'packageName' => $this->whenLoaded('package', fn () => $this->package?->name),
            'status' => $this->status->value,
            'expiredAt' => $this->expired_at?->toDateString(),
            'customerBalance' => (float) $this->customer_balance,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
