<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Package;

class RequirementBuilder
{
    /**
     * Build a final requirement map of inventory_item_id => required_quantity.
     *
     * Expected $data shape:
     * - packages: array of ['package_id' => int, 'quantity' => int]
     * - items: array of ['inventory_item_id' => int, 'quantity' => int]
     * - addons: array of ['addon_id' => int, 'quantity' => int]
     */
    public function build(int $organisationId, array $data): array
    {
        $requirements = [];

        // 1) Expand packages into inventory item quantities
        foreach (($data['packages'] ?? []) as $pkg) {
            $packageId  = (int) ($pkg['package_id'] ?? 0);
            $packageQty = (int) ($pkg['quantity'] ?? 0);

            if ($packageId <= 0 || $packageQty <= 0) {
                continue;
            }

            $package = Package::query()
                ->where('organisation_id', $organisationId)
                ->with('packageItems')
                ->findOrFail($packageId);

            foreach ($package->packageItems as $packageItem) {
                $itemId        = (int) $packageItem->inventory_item_id;
                $perPackageQty = (int) $packageItem->quantity;

                if ($itemId <= 0 || $perPackageQty <= 0) {
                    continue;
                }

                $requirements[$itemId] = ($requirements[$itemId] ?? 0)
                    + ($perPackageQty * $packageQty);
            }
        }

        // 2) Expand addons into inventory item quantities
        foreach (($data['addons'] ?? []) as $a) {
            $addonId  = (int) ($a['addon_id'] ?? 0);
            $addonQty = (int) ($a['quantity'] ?? 0);

            if ($addonId <= 0 || $addonQty <= 0) {
                continue;
            }

            $addon = Addon::query()
                ->where('organisation_id', $organisationId)
                ->where('id', $addonId)
                ->where('is_active', true)
                ->with('items')
                ->firstOrFail();

            // Business rule: addons must reserve inventory (must have addon_items)
            if ($addon->items->isEmpty()) {
                abort(response()->json([
                    'message' => "Addon '{$addon->name}' has no inventory items attached.",
                ], 422));
            }

            foreach ($addon->items as $addonItem) {
                $invId   = (int) $addonItem->inventory_item_id;
                $perUnit = (int) $addonItem->quantity_per_unit;

                if ($invId <= 0 || $perUnit <= 0) {
                    continue;
                }

                $requirements[$invId] = ($requirements[$invId] ?? 0)
                    + ($perUnit * $addonQty);
            }
        }

        // 3) Merge direct items
        foreach (($data['items'] ?? []) as $it) {
            $itemId = (int) ($it['inventory_item_id'] ?? 0);
            $qty    = (int) ($it['quantity'] ?? 0);

            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }

            $requirements[$itemId] = ($requirements[$itemId] ?? 0) + $qty;
        }

        return $requirements;
    }
}