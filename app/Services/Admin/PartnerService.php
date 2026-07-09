<?php

namespace App\Services\Admin;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Collection;

class PartnerService
{
    public function list(): Collection
    {
        return Partner::query()->orderBy('name')->get();
    }

    public function create(array $data): Partner
    {
        return Partner::create([
            'name' => $data['name'],
            'percentage' => $data['percentage'],
        ]);
    }

    public function update(Partner $partner, array $data): Partner
    {
        $update = [];

        if (array_key_exists('name', $data)) {
            $update['name'] = $data['name'];
        }
        if (array_key_exists('percentage', $data)) {
            $update['percentage'] = $data['percentage'];
        }

        $partner->update($update);

        return $partner;
    }

    public function delete(Partner $partner): void
    {
        $partner->delete();
    }
}
