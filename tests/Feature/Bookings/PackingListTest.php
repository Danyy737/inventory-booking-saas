<?php

namespace Tests\Feature\Bookings;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\User;
use App\Models\Organisation;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Booking;

class PackingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_packing_list_groups_by_item_and_sums_required_quantities(): void
    {
        $start = '2026-02-26T10:00:00Z';
        $end   = '2026-02-26T14:00:00Z';

        // --- Arrange: tenant + auth ---
        $org = Organisation::factory()->create();

        $user = User::factory()->create([
            'current_organisation_id' => $org->id,
        ]);

        $org->users()->attach($user->id, ['role' => 'owner']);
        Sanctum::actingAs($user);

        // --- Arrange: items ---
        $mats = InventoryItem::factory()->create([
            'organisation_id' => $org->id,
            'name' => 'Mats',
        ]);

        $castle = InventoryItem::factory()->create([
            'organisation_id' => $org->id,
            'name' => 'Castle',
        ]);

        // Stock not strictly required for packing list, but keeps data realistic
        InventoryStock::factory()->create([
            'organisation_id'   => $org->id,
            'inventory_item_id' => $mats->id,
            'total_quantity'    => 50,
        ]);

        InventoryStock::factory()->create([
            'organisation_id'   => $org->id,
            'inventory_item_id' => $castle->id,
            'total_quantity'    => 50,
        ]);

        // --- Arrange: booking ---
        $booking = Booking::create([
            'organisation_id' => $org->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => 'confirmed',
        ]);

        // Create multiple reservations for the same item to force aggregation
        $mats->reservations()->create([
            'organisation_id' => $org->id,
            'booking_id' => $booking->id,
            'reserved_quantity' => 3,
            'start_at' => $start,
            'end_at' => $end,
            'status' => 'active',
        ]);

        $mats->reservations()->create([
            'organisation_id' => $org->id,
            'booking_id' => $booking->id,
            'reserved_quantity' => 5,
            'start_at' => $start,
            'end_at' => $end,
            'status' => 'active',
        ]);

        $castle->reservations()->create([
            'organisation_id' => $org->id,
            'booking_id' => $booking->id,
            'reserved_quantity' => 2,
            'start_at' => $start,
            'end_at' => $end,
            'status' => 'active',
        ]);

        // --- Act ---
        $res = $this->getJson("/api/bookings/{$booking->id}/packing-list");

        // --- Assert ---
        $res->assertOk();

        // Endpoint returns: { booking: {...}, packing_list: [...], summary: {...} }
        $packingList = $res->json('packing_list');

        $this->assertIsArray($packingList, 'Expected packing_list to be an array.');

        $matsRow = collect($packingList)->firstWhere('inventory_item_id', $mats->id);
        $castleRow = collect($packingList)->firstWhere('inventory_item_id', $castle->id);

        $this->assertNotNull($matsRow, 'Expected packing list to include mats.');
        $this->assertNotNull($castleRow, 'Expected packing list to include castle.');

        // Mats should be 3 + 5 = 8 (controller returns required_quantity)
        $this->assertEquals(8, $matsRow['required_quantity'] ?? null, 'Expected mats required_quantity to sum to 8.');
        $this->assertEquals(2, $castleRow['required_quantity'] ?? null, 'Expected castle required_quantity to be 2.');

        // Optional: summary assertions (locks the contract)
        $res->assertJsonPath('summary.unique_items', 2);
        $res->assertJsonPath('summary.total_units', 10);
    }
}
