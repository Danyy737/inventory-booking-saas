<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Users that belong to this organisation
     */
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Inventory items owned by this organisation
     */
    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }
}
