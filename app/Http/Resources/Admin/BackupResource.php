<?php

namespace App\Http\Resources\Admin;

use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Backup to match the Angular `Backup` interface in
 * D:\Angular\Hawalaty\src\app\data\backups.ts.
 *
 * @mixin Backup
 */
class BackupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'licenseId' => $this->license_id ? (string) $this->license_id : null,
            'licenseCode' => $this->whenLoaded('license', fn () => $this->license?->license_code),
            'createdAt' => $this->created_at?->toISOString(),
            'sizeMb' => $this->size_mb,
            'trigger' => $this->trigger->value,
            'actorName' => $this->actor_name ?? '',
            'status' => $this->status->value,
            'hasFile' => (bool) $this->file_path,
        ];
    }
}
