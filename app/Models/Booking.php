<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'organisation_id',
        'reference',
        'start_at',
        'end_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function reservations()
    {
        return $this->hasMany(InventoryReservation::class);
    }
    
    public function addons()
{
    return $this->hasMany(BookingAddon::class);
}
}



