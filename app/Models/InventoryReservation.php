<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryReservation extends Model
{
    protected $fillable = [
        'organisation_id',
        'inventory_item_id',
        'booking_id',
        'reserved_quantity',
        'start_at',
        'end_at',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
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
