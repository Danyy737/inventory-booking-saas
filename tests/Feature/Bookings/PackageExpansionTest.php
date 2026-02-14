<?php

namespace Tests\Feature\Bookings;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\User;
use App\Models\Organisation;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Package;
use App\Models\PackageItem;

class PackageExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_expands_packages_and_merges_addons_into_single_requirement(): void
    {
        // Window doesn't matter here beyond being valid
        $start = '2026-02-24T10:00:00Z';
        $end   = '2026-02-24T14:00:00Z';

        // --- Arrange: tenant + auth ---
        $org = Organisation::factory()->create();

        $user = User::factory()->create([
            'current_organisation_id' => $org->id,
        ]);

        $org->users()->attach($user->id, ['role' => 'owner']);
        Sanctum::actingAs($user);

        // --- Arrange: inventory items ---
        $mats = InventoryItem::factory()->create([
            'organisation_id' => $org->id,
            'name' => 'Mats',
        ]);

        $ballPit = InventoryItem::factory()->create([
            'organisation_id' => $org->id,
            'name' => 'Ball Pit',
        ]);

        // Plenty of stock so availability should be true
        InventoryStock::factory()->create([
            'organisation_id'   => $org->id,
            'inventory_item_id' => $mats->id,
            'total_quantity'    => 50,
        ]);

        InventoryStock::factory()->create([
            'organisation_id'   => $org->id,
            'inventory_item_id' => $ballPit->id,
            'total_quantity'    => 50,
        ]);

        // --- Arrange: package (Ball Pit package contains 5 mats + 1 ball pit item) ---
        // If your Package model has different required fields, adjust here.
        $package = Package::create([
            'organisation_id' => $org->id,
            'name' => 'Ball Pit Package',
        ]);

        PackageItem::create([
            'package_id' => $package->id,
            'inventory_item_id' => $mats->id,
            'quantity' => 5,
        ]);

        PackageItem::create([
            'package_id' => $package->id,
            'inventory_item_id' => $ballPit->id,
            'quantity' => 1,
        ]);

        // Request: 2 packages + add-on 3 mats
        // Mats should be: (5 * 2) + 3 = 13
        // Ball Pit should be: (1 * 2) = 2
        $payload = [
            'start_at' => $start,
            'end_at'   => $end,
            'packages' => [
                ['package_id' => $package->id, 'quantity' => 2],
            ],
            'items' => [
                ['inventory_item_id' => $mats->id, 'quantity' => 3],
            ],
        ];

        // --- Act ---
        $res = $this->postJson('/api/bookings/preview-availability', $payload);

        // --- Assert ---
        $res->assertOk();
        $res->assertJsonPath('available', true);

        $json = $res->json();

        $this->assertIsArray($json['requirements'] ?? null, 'Expected requirements to be an array.');

        $matsReq = collect($json['requirements'])->firstWhere('inventory_item_id', $mats->id);
        $ballPitReq = collect($json['requirements'])->firstWhere('inventory_item_id', $ballPit->id);

        $this->assertNotNull($matsReq, 'Expected requirements to include mats.');
        $this->assertNotNull($ballPitReq, 'Expected requirements to include ball pit.');

        $this->assertEquals(13, $matsReq['required_quantity'] ?? null, 'Expected mats required_quantity to be 13.');
        $this->assertEquals(2, $ballPitReq['required_quantity'] ?? null, 'Expected ball pit required_quantity to be 2.');
    }
}
