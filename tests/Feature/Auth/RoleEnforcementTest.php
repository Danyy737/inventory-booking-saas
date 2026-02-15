<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\User;
use App\Models\Organisation;
use App\Models\InventoryItem;
use App\Models\InventoryStock;

class RoleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_create_bookings(): void
    {
        $start = '2026-02-27T10:00:00Z';
        $end   = '2026-02-27T14:00:00Z';

        // --- Arrange: org + STAFF user ---
        $org = Organisation::factory()->create();

        $user = User::factory()->create([
            'current_organisation_id' => $org->id,
        ]);

        $org->users()->attach($user->id, ['role' => 'staff']);
        Sanctum::actingAs($user);

        // Create an item + stock so the request would otherwise be valid
        $item = InventoryItem::factory()->create([
            'organisation_id' => $org->id,
        ]);

        InventoryStock::factory()->create([
            'organisation_id'   => $org->id,
            'inventory_item_id' => $item->id,
            'total_quantity'    => 10,
        ]);

        // --- Act: staff tries to store booking ---
        $res = $this->postJson('/api/bookings', [
            'start_at' => $start,
            'end_at'   => $end,
            'packages' => [],
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

        // --- Assert: forbidden ---
        $res->assertForbidden();

        // Ensure nothing was written
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
    }


    public function test_staff_cannot_cancel_bookings(): void
{
    $start = '2026-02-28T10:00:00Z';
    $end   = '2026-02-28T14:00:00Z';

    // --- Arrange: org + STAFF user ---
    $org = Organisation::factory()->create();

    $staff = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);

    $org->users()->attach($staff->id, ['role' => 'staff']);

    // Create item + stock
    $item = InventoryItem::factory()->create([
        'organisation_id' => $org->id,
    ]);

    InventoryStock::factory()->create([
        'organisation_id'   => $org->id,
        'inventory_item_id' => $item->id,
        'total_quantity'    => 10,
    ]);

    // Create a booking as OWNER (so booking exists)
    $owner = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);
    $org->users()->attach($owner->id, ['role' => 'owner']);

    Sanctum::actingAs($owner);

    $store = $this->postJson('/api/bookings', [
        'start_at' => $start,
        'end_at'   => $end,
        'packages' => [],
        'items' => [
            ['inventory_item_id' => $item->id, 'quantity' => 1],
        ],
    ]);

    $store->assertStatus(201);
    $bookingId = $store->json('data.id');
    $this->assertNotNull($bookingId);

    // --- Act: STAFF tries to cancel ---
    Sanctum::actingAs($staff);

    $cancel = $this->patchJson("/api/bookings/{$bookingId}/cancel");

    // --- Assert: forbidden + booking unchanged ---
    $cancel->assertForbidden();

    // Booking should NOT be cancelled
    $this->assertDatabaseHas('bookings', [
        'id' => $bookingId,
        'status' => 'confirmed',
    ]);

    // No reservation should be made inactive by staff
    $this->assertDatabaseHas('inventory_reservations', [
        'booking_id' => $bookingId,
        'inventory_item_id' => $item->id,
        'status' => 'active',
    ]);
}



public function test_staff_cannot_create_inventory_items(): void
{
    $org = Organisation::factory()->create();

    $staff = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);

    $org->users()->attach($staff->id, ['role' => 'staff']);
    Sanctum::actingAs($staff);

    $res = $this->postJson('/api/inventory/items', [
        'name' => 'New Item',
        'sku' => 'SKU-TEST-001',
        'description' => 'Test item',
        'is_active' => true,
    ]);

    $res->assertForbidden();

    // Ensure nothing was created for this org
    $this->assertDatabaseMissing('inventory_items', [
        'organisation_id' => $org->id,
        'sku' => 'SKU-TEST-001',
    ]);
}

}
