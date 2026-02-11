<?php

namespace App\Services;

use App\Models\InventoryItem;

class AvailabilityService
{
    /**
     * @param  int   $orgId
     * @param  string|\DateTimeInterface  $startAt
     * @param  string|\DateTimeInterface  $endAt
     * @param  array<int,int>  $requiredByItemId  [inventory_item_id => required_qty]
     * @param  int|null  $excludeBookingId  When editing a booking, exclude its existing reservations
     */
    public function check(
        int $orgId,
        $startAt,
        $endAt,
        array $requiredByItemId,
        ?int $excludeBookingId = null
    ): array {
        $availability = [];

        foreach ($requiredByItemId as $itemId => $requiredQty) {
            $itemId = (int) $itemId;
            $requiredQty = (int) $requiredQty;

            $item = InventoryItem::query()
                ->where('organisation_id', $orgId)
                ->where('id', $itemId)
                ->with('stock')
                ->firstOrFail();

            $total = (int) optional($item->stock)->total_quantity;

            $reservationsQuery = $item->reservations()
                ->where('organisation_id', $orgId)
                ->where('status', 'active')
                ->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt);

            if ($excludeBookingId) {
                $reservationsQuery->where('booking_id', '!=', $excludeBookingId);
            }

            $reserved = (int) $reservationsQuery->sum('reserved_quantity');

            $availableQty = max(0, $total - $reserved);
            $shortBy = max(0, $requiredQty - $availableQty);

            $availability[] = [
                'inventory_item_id' => $itemId,
                'required_quantity' => $requiredQty,
                'available_quantity' => $availableQty,
                'short_by' => $shortBy,
            ];
        }

        $shortages = array_values(array_filter($availability, fn ($row) => $row['short_by'] > 0));

        return [
            'available' => count($shortages) === 0,
            'availability' => $availability,
            'shortages' => $shortages,
        ];
    }
}
