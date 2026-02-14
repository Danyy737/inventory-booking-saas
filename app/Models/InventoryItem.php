<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'name',
        'sku',
        'description',
        'is_active',
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stock()
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function reservations()
    {
        return $this->hasMany(InventoryReservation::class);
    }
}
