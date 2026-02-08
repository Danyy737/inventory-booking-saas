<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryStock extends Model
{
    protected $table = 'inventory_stock';

    protected $fillable = [
        'organisation_id',
        'inventory_item_id',
        'total_quantity',
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
