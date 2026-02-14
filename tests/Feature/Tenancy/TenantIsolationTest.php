<?php

namespace Tests\Feature\Tenancy;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\User;
use App\Models\Organisation;
use App\Models\InventoryItem;
use App\Models\InventoryStock;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservations_in_org_a_do_not_affect_org_b_availability(): void
    {
        $start = '2026-02-23T10:00:00Z';
        $end   = '2026-02-23T14:00:00Z';

        // --- Arrange: Org A + user A (owner) ---
        $orgA = Organisation::factory()->create();
        $userA = User::factory()->create(['current_organisation_id' => $orgA->id]);
        $orgA->users()->attach($userA->id, ['role' => 'owner']);

        $itemA = InventoryItem::factory()->create(['organisation_id' => $orgA->id]);
        InventoryStock::factory()->create([
            'organisation_id'   => $orgA->id,
            'inventory_item_id' => $itemA->id,
            'total_quantity'    => 5,
        ]);

        // --- Arrange: Org B + user B (owner) ---
        $orgB = Organisation::factory()->create();
        $userB = User::factory()->create(['current_organisation_id' => $orgB->id]);
        $orgB->users()->attach($userB->id, ['role' => 'owner']);

        $itemB = InventoryItem::factory()->create(['organisation_id' => $orgB->id]);
        InventoryStock::factory()->create([
            'organisation_id'   => $orgB->id,
            'inventory_item_id' => $itemB->id,
            'total_quantity'    => 5,
        ]);

        // --- Act 1: Create booking in Org A that uses all stock ---
        Sanctum::actingAs($userA);

        $storeA = $this->postJson('/api/bookings', [
            'start_at' => $start,
            'end_at'   => $end,
            'packages' => [],
            'items' => [
                ['inventory_item_id' => $itemA->id, 'quantity' => 5],
            ],
        ]);

        $storeA->assertStatus(201);

        // --- Act 2: Preview in Org B for same window should still be available ---
        Sanctum::actingAs($userB);

        $previewB = $this->postJson('/api/bookings/preview-availability', [
            'start_at' => $start,
            'end_at'   => $end,
            'packages' => [],
            'items' => [
                ['inventory_item_id' => $itemB->id, 'quantity' => 5],
            ],
        ]);

        $previewB->assertOk();
        $previewB->assertJsonPath('available', true);
    }
}
