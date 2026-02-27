<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingAddon extends Model
{
    protected $fillable = [
        'booking_id',
        'addon_id',
        'quantity',
        'unit_price_cents',
        'line_total_cents',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }
}