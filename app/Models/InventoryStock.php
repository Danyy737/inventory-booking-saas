<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryStock extends Model
{
    use HasFactory;

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
