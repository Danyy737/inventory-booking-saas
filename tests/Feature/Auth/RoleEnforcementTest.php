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


public function test_staff_cannot_update_bookings(): void
{
    $start = '2026-03-03T10:00:00Z';
    $end   = '2026-03-03T14:00:00Z';

    // --- Arrange: org + owner (to create booking) ---
    $org = Organisation::factory()->create();

    $owner = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);
    $org->users()->attach($owner->id, ['role' => 'owner']);

    $item = InventoryItem::factory()->create([
        'organisation_id' => $org->id,
    ]);

    InventoryStock::factory()->create([
        'organisation_id'   => $org->id,
        'inventory_item_id' => $item->id,
        'total_quantity'    => 10,
    ]);

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

    // --- Arrange: staff user ---
    $staff = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);
    $org->users()->attach($staff->id, ['role' => 'staff']);
    Sanctum::actingAs($staff);

    // --- Act: staff attempts to update booking ---
    $update = $this->patchJson("/api/bookings/{$bookingId}", [
        'start_at' => $start,
        'end_at'   => $end,
        'packages' => [],
        'items' => [
            ['inventory_item_id' => $item->id, 'quantity' => 2],
        ],
    ]);

    // --- Assert: forbidden + no change in DB reservations ---
    $update->assertForbidden();

    // Booking should remain confirmed
    $this->assertDatabaseHas('bookings', [
        'id' => $bookingId,
        'status' => 'confirmed',
    ]);

    // Reservation should still be active quantity 1 (not changed to 2)
    $this->assertDatabaseHas('inventory_reservations', [
        'booking_id' => $bookingId,
        'inventory_item_id' => $item->id,
        'reserved_quantity' => 1,
        'status' => 'active',
    ]);
}
public function test_staff_can_preview_availability(): void
{
    $org = Organisation::factory()->create();

    $staff = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);
    $org->users()->attach($staff->id, ['role' => 'staff']);
    Sanctum::actingAs($staff);

    $item = InventoryItem::factory()->create([
        'organisation_id' => $org->id,
    ]);

    InventoryStock::factory()->create([
        'organisation_id'   => $org->id,
        'inventory_item_id' => $item->id,
        'total_quantity'    => 5,
    ]);

    $res = $this->postJson('/api/bookings/preview-availability', [
        'start_at' => '2026-03-04T10:00:00Z',
        'end_at'   => '2026-03-04T14:00:00Z',
        'packages' => [],
        'items' => [
            ['inventory_item_id' => $item->id, 'quantity' => 1],
        ],
    ]);

    $res->assertOk();
    $res->assertJsonPath('available', true);
}
public function test_staff_can_view_packing_list(): void
{
    $org = Organisation::factory()->create();

    $owner = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);
    $org->users()->attach($owner->id, ['role' => 'owner']);

    Sanctum::actingAs($owner);

    $item = InventoryItem::factory()->create([
        'organisation_id' => $org->id,
    ]);

    InventoryStock::factory()->create([
        'organisation_id' => $org->id,
        'inventory_item_id' => $item->id,
        'total_quantity' => 10,
    ]);

    $store = $this->postJson('/api/bookings', [
        'start_at' => '2026-03-05T10:00:00Z',
        'end_at'   => '2026-03-05T14:00:00Z',
        'packages' => [],
        'items' => [
            ['inventory_item_id' => $item->id, 'quantity' => 2],
        ],
    ]);

    $bookingId = $store->json('data.id');

    $staff = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);
    $org->users()->attach($staff->id, ['role' => 'staff']);
    Sanctum::actingAs($staff);

    $res = $this->getJson("/api/bookings/{$bookingId}/packing-list");

    $res->assertOk();
}
public function test_staff_cannot_update_inventory_items(): void
{
    $org = Organisation::factory()->create();

    $staff = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);
    $org->users()->attach($staff->id, ['role' => 'staff']);
    Sanctum::actingAs($staff);

    $item = InventoryItem::factory()->create([
        'organisation_id' => $org->id,
    ]);

    $res = $this->patchJson("/api/inventory/items/{$item->id}", [
        'name' => 'Updated Name',
    ]);

    $res->assertForbidden();
}
public function test_staff_cannot_delete_inventory_items(): void
{
    $org = Organisation::factory()->create();

    $staff = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);
    $org->users()->attach($staff->id, ['role' => 'staff']);
    Sanctum::actingAs($staff);

    $item = InventoryItem::factory()->create([
        'organisation_id' => $org->id,
    ]);

    $res = $this->deleteJson("/api/inventory/items/{$item->id}");

    $res->assertForbidden();
}
public function test_staff_cannot_update_packages(): void
{
    $org = Organisation::factory()->create();

    $staff = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);
    $org->users()->attach($staff->id, ['role' => 'staff']);
    Sanctum::actingAs($staff);

    $package = \App\Models\Package::create([
        'organisation_id' => $org->id,
        'name' => 'Test Package',
    ]);

    $res = $this->patchJson("/api/packages/{$package->id}", [
        'name' => 'Updated Package',
    ]);

    $res->assertForbidden();
}


}
