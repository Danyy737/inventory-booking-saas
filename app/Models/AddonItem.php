<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddonItem extends Model
{
    protected $fillable = [
        'addon_id',
        'inventory_item_id',
        'quantity_per_unit',
    ];

    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}