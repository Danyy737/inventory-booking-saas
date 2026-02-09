<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PackageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'inventory_item_id',
        'quantity',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
