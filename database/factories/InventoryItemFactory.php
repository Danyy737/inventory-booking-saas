<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => $this->faker->words(3, true),
            'sku' => strtoupper($this->faker->bothify('SKU-####')),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
