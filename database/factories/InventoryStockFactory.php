<?php

namespace Database\Factories;

use App\Models\InventoryStock;
use App\Models\Organisation;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryStockFactory extends Factory
{
    protected $model = InventoryStock::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'total_quantity' => 10,
        ];
    }
}
