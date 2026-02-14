<?php

namespace Tests\Feature\Bookings;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

use App\Models\User;
use App\Models\Organisation;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Booking;
use App\Models\InventoryReservation;

class StoreBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_returns_201_and_creates_booking_and_reservations_when_available(): void
    {
        // --- Arrange: tenant + auth (admin-like required) ---
        $org = Organisation::factory()->create();

        $user = User::factory()->create([
            'current_organisation_id' => $org->id,
        ]);

        // store() calls ensureAdminLike(), so use owner/admin
        $org->users()->attach($user->id, ['role' => 'owner']);

        Sanctum::actingAs($user);

        // --- Arrange: inventory + stock ---
        $item = InventoryItem::factory()->create([
            'organisation_id' => $org->id,
        ]);

        InventoryStock::factory()->create([
            'organisation_id'   => $org->id,
            'inventory_item_id' => $item->id,
            'total_quantity'    => 10,
        ]);

        $start = Carbon::parse('2026-02-21 10:00:00')->toISOString();
        $end   = Carbon::parse('2026-02-21 14:00:00')->toISOString();

        $payload = [
            'start_at' => $start,
            'end_at'   => $end,
            'packages' => [],
            'items' => [
                [
                    'inventory_item_id' => $item->id,
                    'quantity' => 7,
                ],
            ],
        ];

        // --- Act ---
        $res = $this->postJson('/api/bookings', $payload);

        // --- Assert: response ---
        $res->assertStatus(201);
        $res->assertJsonPath('data.organisation_id', $org->id);
        $res->assertJsonPath('data.status', 'confirmed');

        // --- Assert: DB write ---
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);

        $bookingId = $res->json('data.id');

        $this->assertNotNull($bookingId, 'Expected response to include booking id.');

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'organisation_id' => $org->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('inventory_reservations', [
            'booking_id' => $bookingId,
            'organisation_id' => $org->id,
            'inventory_item_id' => $item->id,
            'reserved_quantity' => 7,
            'status' => 'active',
        ]);
    }
}
