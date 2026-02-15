<?php

namespace App\Services;

use App\Models\InventoryReservation;
use App\Models\InventoryStock;
use Carbon\Carbon;

class AvailabilityService
{
    /**
     * Check availability for a given org + time window + required quantities.
     *
     * @param  int        $orgId
     * @param  mixed      $startAt  (string|Carbon|\DateTimeInterface)
     * @param  mixed      $endAt    (string|Carbon|\DateTimeInterface)
     * @param  array      $required [inventory_item_id => required_quantity]
     * @param  int|null   $excludeBookingId Used for updates (ignore this booking's existing reservations)
     *
     * @return array{
     *   available: bool,
     *   availability: array<int, array{inventory_item_id:int, required_quantity:int, available_quantity:int, short_by:int}>,
     *   shortages: array<int, array{inventory_item_id:int, required_quantity:int, available_quantity:int, short_by:int}>
     * }
     */
    public function check(
        int $orgId,
        $startAt,
        $endAt,
        array $required,
        ?int $excludeBookingId = null
    ): array {
        $start = Carbon::parse($startAt);
        $end   = Carbon::parse($endAt);

        // Normalize: ignore non-positive requirements defensively
        $required = collect($required)
            ->map(fn ($qty) => (int) $qty)
            ->filter(fn ($qty) => $qty > 0)
            ->toArray();

        if (empty($required)) {
            return [
                'available' => true,
                'availability' => [],
                'shortages' => [],
            ];
        }

        // Load stock totals (your schema uses inventory_stock + total_quantity)
        $stocks = InventoryStock::query()
            ->where('organisation_id', $orgId)
            ->whereIn('inventory_item_id', array_keys($required))
            ->get(['inventory_item_id', 'total_quantity'])
            ->keyBy('inventory_item_id');

        $availability = [];
        $shortages = [];
        $allAvailable = true;

        foreach ($required as $itemId => $requiredQty) {
            $itemId = (int) $itemId;
            $requiredQty = (int) $requiredQty;

            $totalStock = (int) optional($stocks->get($itemId))->total_quantity;

            // Sum overlapping ACTIVE reservations for this item in this org and time window
            $reservedInWindow = (int) InventoryReservation::query()
                ->where('organisation_id', $orgId)
                ->where('inventory_item_id', $itemId)
                ->where('status', 'active')
                ->when($excludeBookingId, fn ($q) => $q->where('booking_id', '!=', $excludeBookingId))
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->sum('reserved_quantity');

            $availableQty = max(0, $totalStock - $reservedInWindow);
            $shortBy = max(0, $requiredQty - $availableQty);

            $row = [
                'inventory_item_id' => $itemId,
                'required_quantity' => $requiredQty,
                'available_quantity' => $availableQty,
                'short_by' => $shortBy,
            ];

            $availability[] = $row;

            if ($shortBy > 0) {
                $allAvailable = false;
                $shortages[] = $row;
            }
        }

        return [
            'available' => $allAvailable,
            'availability' => $availability,
            'shortages' => $shortages,
        ];
    }
}
