<?php

namespace App\Services\Admin;

use App\Models\Box;
use Illuminate\Database\Eloquent\Collection;

class BoxService
{
    public function list(): Collection
    {
        return Box::query()->orderBy('box_name')->get();
    }

    public function create(array $data): Box
    {
        return Box::create([
            'box_name' => $data['name'],
            'box_balance' => $data['balance'] ?? 0,
        ]);
    }

    public function update(Box $box, array $data): Box
    {
        $update = [];

        if (array_key_exists('name', $data)) {
            $update['box_name'] = $data['name'];
        }
        if (array_key_exists('balance', $data)) {
            $update['box_balance'] = $data['balance'];
        }

        $box->update($update);

        return $box;
    }

    public function delete(Box $box): void
    {
        $box->delete();
    }
}
