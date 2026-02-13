<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Collection;

class RequirementBuilder
{
    /**
     * Build a final requirement map of inventory_item_id => required_quantity.
     *
     * Expected $data shape:
     * - packages: array of ['package_id' => int, 'quantity' => int]
     * - items: array of ['inventory_item_id' => int, 'quantity' => int]
     */
    public function build(int $organisationId, array $data): array
    {
        $requirements = [];

        // 1) Expand packages into inventory item quantities
        foreach (($data['packages'] ?? []) as $pkg) {
            $packageId = (int)($pkg['package_id'] ?? 0);
            $packageQty = (int)($pkg['quantity'] ?? 0);

            if ($packageId <= 0 || $packageQty <= 0) {
                continue;
            }

          $package = Package::query()
    ->where('organisation_id', $organisationId)
    ->with('packageItems')
    ->findOrFail($packageId);

foreach ($package->packageItems as $packageItem) {
    $itemId = (int) $packageItem->inventory_item_id;
    $perPackageQty = (int) $packageItem->quantity;

    if ($itemId <= 0 || $perPackageQty <= 0) {
        continue;
    }

    $requirements[$itemId] = ($requirements[$itemId] ?? 0)
        + ($perPackageQty * $packageQty);
}

        // 2) Merge direct items (add-ons)
        foreach (($data['items'] ?? []) as $it) {
            $itemId = (int)($it['inventory_item_id'] ?? 0);
            $qty = (int)($it['quantity'] ?? 0);

            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }

            $requirements[$itemId] = ($requirements[$itemId] ?? 0) + $qty;
        }

        return $requirements;
    }
}
}