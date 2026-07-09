<?php

namespace App\Services\Admin;

use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;

class PackageService
{
    public function list(): Collection
    {
        return Package::query()->orderBy('price')->get();
    }

    public function create(array $data): Package
    {
        return Package::create([
            'name' => $data['name'],
            'price' => $data['price'],
            'device_limit' => $data['deviceLimit'],
            'features' => $data['features'] ?? [],
        ]);
    }

    public function update(Package $package, array $data): Package
    {
        $update = [];

        if (array_key_exists('name', $data)) {
            $update['name'] = $data['name'];
        }
        if (array_key_exists('price', $data)) {
            $update['price'] = $data['price'];
        }
        if (array_key_exists('deviceLimit', $data)) {
            $update['device_limit'] = $data['deviceLimit'];
        }
        if (array_key_exists('features', $data)) {
            $update['features'] = $data['features'] ?? [];
        }

        $package->update($update);

        return $package;
    }

    public function delete(Package $package): void
    {
        $package->delete();
    }
}
