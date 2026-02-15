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
}
