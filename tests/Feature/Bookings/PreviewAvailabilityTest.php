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

class PreviewAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_availability_returns_200_and_shortages_when_insufficient(): void
    {
        // WHY: Preview must always be 200, and must explain shortages reliably.

        // --- Arrange: tenant + auth context ---
        $org = Organisation::factory()->create();

        $user = User::factory()->create([
            'current_organisation_id' => $org->id,
        ]);

        // Pivot membership + role (tenant middleware usually expects this)
        $org->users()->attach($user->id, ['role' => 'owner']);

        Sanctum::actingAs($user);

        // --- Arrange: inventory + stock ---
        $item = InventoryItem::factory()->create([
            'organisation_id' => $org->id,
        ]);

      InventoryStock::factory()->create([
    'organisation_id'   => $org->id,
    'inventory_item_id' => $item->id,
    'total_quantity'    => 5,
]);


        // Need 7, only have 5 => shortage should be 2
        $payload = [
            'start_at' => Carbon::parse('2026-02-20 10:00:00')->toISOString(),
            'end_at'   => Carbon::parse('2026-02-20 14:00:00')->toISOString(),
            'packages' => [],
            'items' => [
                [
                    'inventory_item_id' => $item->id,
                    'quantity' => 7,
                ],
            ],
        ];

        // --- Act ---
        $res = $this->postJson('/api/bookings/preview-availability', $payload);

        // --- Assert ---
       


        // These keys depend on your controller response.
        // If your keys differ, we’ll adjust once you paste the JSON response.
        $res->assertJsonPath('available', false);

        // Assert shortages contains this item with shortage 2
        // We’ll make this robust: check the shortages array has an entry for item id and shortage=2.
        $json = $res->json();

        $this->assertIsArray($json['shortages'] ?? null, 'Expected shortages to be an array.');

        $shortageRow = collect($json['shortages'])->firstWhere('inventory_item_id', $item->id);

        $this->assertNotNull($shortageRow, 'Expected shortages to include the inventory item.');
        $this->assertEquals(2, $shortageRow['short_by'] ?? null, 'Expected shortage_quantity to be 2.');
    }
}
