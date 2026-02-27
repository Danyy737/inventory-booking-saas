<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = [
        'organisation_id',
        'name',
        'description',
        'pricing_type',
        'price_cents',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function items()
    {
        return $this->hasMany(AddonItem::class);
    }

    public function bookingAddons()
    {
        return $this->hasMany(BookingAddon::class);
    }
}