<?php

namespace Tests\Feature\Bookings;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\User;
use App\Models\Organisation;
use App\Models\InventoryItem;
use App\Models\InventoryStock;

class CancelBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_releases_reservations_and_restores_availability(): void
    {
        $start = '2026-02-25T10:00:00Z';
        $end   = '2026-02-25T14:00:00Z';

        // --- Arrange: tenant + auth (admin-like) ---
        $org = Organisation::factory()->create();

        $user = User::factory()->create([
            'current_organisation_id' => $org->id,
        ]);

        $org->users()->attach($user->id, ['role' => 'owner']);
        Sanctum::actingAs($user);

        // --- Arrange: item with stock 5 ---
        $item = InventoryItem::factory()->create([
            'organisation_id' => $org->id,
        ]);

        InventoryStock::factory()->create([
            'organisation_id'   => $org->id,
            'inventory_item_id' => $item->id,
            'total_quantity'    => 5,
        ]);

        // --- Act 1: create booking that consumes all 5 ---
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
        $this->assertNotNull($bookingId, 'Expected booking id from store response.');

        // --- Assert 1: preview now unavailable even for 1 more ---
        $previewBefore = $this->postJson('/api/bookings/preview-availability', [
            'start_at' => $start,
            'end_at'   => $end,
            'packages' => [],
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

        $previewBefore->assertOk();
        $previewBefore->assertJsonPath('available', false);

        // --- Act 2: cancel the booking ---
        $cancel = $this->patchJson("/api/bookings/{$bookingId}/cancel");

        $cancel->assertOk();

        // --- Assert 2: preview available again after cancel ---
        $previewAfter = $this->postJson('/api/bookings/preview-availability', [
            'start_at' => $start,
            'end_at'   => $end,
            'packages' => [],
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

        $previewAfter->assertOk();
        $previewAfter->assertJsonPath('available', true);

        // --- Assert 3: no ACTIVE reservation remains for this booking ---
        // (Passes if reservations are cancelled/inactive OR deleted)
        $this->assertDatabaseMissing('inventory_reservations', [
            'booking_id' => $bookingId,
            'inventory_item_id' => $item->id,
            'status' => 'active',
        ]);
    }
}
