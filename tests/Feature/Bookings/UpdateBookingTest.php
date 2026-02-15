<?php

namespace Tests\Feature\Bookings;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\User;
use App\Models\Organisation;
use App\Models\InventoryItem;
use App\Models\InventoryStock;

class UpdateBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_does_not_block_itself_and_rewrites_reservations(): void
    {
        $start = '2026-03-01T10:00:00Z';
        $end   = '2026-03-01T14:00:00Z';

        // --- Arrange: tenant + auth (owner/admin) ---
        $org = Organisation::factory()->create();

        $user = User::factory()->create([
            'current_organisation_id' => $org->id,
        ]);

        $org->users()->attach($user->id, ['role' => 'owner']);
        Sanctum::actingAs($user);

        // --- Arrange: item + stock exactly 5 ---
        $item = InventoryItem::factory()->create([
            'organisation_id' => $org->id,
        ]);

        InventoryStock::factory()->create([
            'organisation_id'   => $org->id,
            'inventory_item_id' => $item->id,
            'total_quantity'    => 5,
        ]);

        // --- Act 1: create booking that uses all 5 ---
        $store = $this->postJson('/api/bookings', [
            'start_at' => $start,
            'end_at'   => $end,
            'packages' => [],
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 5],
            ],
        ]);

        $store->assertStatus(201);
        $bookingId = $store->json('data.id');
        $this->assertNotNull($bookingId);

        // --- Act 2: update booking with SAME requirements ---
        // Without excludeBookingId support, this often fails with 409 (self-block).
        $update = $this->patchJson("/api/bookings/{$bookingId}", [
            'start_at' => $start,
            'end_at'   => $end,
            'packages' => [],
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 5],
            ],
        ]);

        $update->assertOk();
        $update->assertJsonPath('data.id', $bookingId);

        // --- Assert: no doubled ACTIVE reservations ---
        // After update, old reservations should be cancelled and a fresh active one created.
        $this->assertDatabaseCount('bookings', 1);

        $activeCount = \DB::table('inventory_reservations')
            ->where('booking_id', $bookingId)
            ->where('inventory_item_id', $item->id)
            ->where('status', 'active')
            ->count();

        $this->assertEquals(1, $activeCount, 'Expected exactly 1 active reservation after update.');

        // And the active reservation should still be quantity 5
        $this->assertDatabaseHas('inventory_reservations', [
            'booking_id' => $bookingId,
            'inventory_item_id' => $item->id,
            'reserved_quantity' => 5,
            'status' => 'active',
        ]);
    }

    public function test_update_returns_409_when_new_window_conflicts_with_another_booking(): void
{
    $org = Organisation::factory()->create();

    $user = User::factory()->create([
        'current_organisation_id' => $org->id,
    ]);

    $org->users()->attach($user->id, ['role' => 'owner']);
    Sanctum::actingAs($user);

    $item = InventoryItem::factory()->create([
        'organisation_id' => $org->id,
    ]);

    InventoryStock::factory()->create([
        'organisation_id'   => $org->id,
        'inventory_item_id' => $item->id,
        'total_quantity'    => 5,
    ]);

    // Booking A window
    $startA = '2026-03-02T10:00:00Z';
    $endA   = '2026-03-02T14:00:00Z';

    // Booking B original (non-overlapping) window
    $startB = '2026-03-02T16:00:00Z';
    $endB   = '2026-03-02T18:00:00Z';

    // 1) Create Booking A consuming all stock in window A
    $storeA = $this->postJson('/api/bookings', [
        'start_at' => $startA,
        'end_at'   => $endA,
        'packages' => [],
        'items' => [
            ['inventory_item_id' => $item->id, 'quantity' => 5],
        ],
    ]);
    $storeA->assertStatus(201);

    // 2) Create Booking B consuming all stock in window B (non-overlapping so OK)
    $storeB = $this->postJson('/api/bookings', [
        'start_at' => $startB,
        'end_at'   => $endB,
        'packages' => [],
        'items' => [
            ['inventory_item_id' => $item->id, 'quantity' => 5],
        ],
    ]);
    $storeB->assertStatus(201);

    $bookingBId = $storeB->json('data.id');
    $this->assertNotNull($bookingBId);

    // 3) Try to update Booking B into window A -> should conflict with Booking A
    $update = $this->patchJson("/api/bookings/{$bookingBId}", [
        'start_at' => $startA,
        'end_at'   => $endA,
        'packages' => [],
        'items' => [
            ['inventory_item_id' => $item->id, 'quantity' => 5],
        ],
    ]);

    $update->assertStatus(409);
    $update->assertJsonPath('message', 'Insufficient availability');

    // 4) Ensure Booking B DID NOT change to window A (no partial update)
    $this->assertDatabaseHas('bookings', [
        'id' => $bookingBId,
        'start_at' => $startB,
        'end_at' => $endB,
        'status' => 'confirmed',
    ]);

    // 5) Ensure Booking B still has an ACTIVE reservation in its original window B
    $this->assertDatabaseHas('inventory_reservations', [
        'booking_id' => $bookingBId,
        'inventory_item_id' => $item->id,
        'reserved_quantity' => 5,
        'status' => 'active',
        'start_at' => $startB,
        'end_at' => $endB,
    ]);
}

}
