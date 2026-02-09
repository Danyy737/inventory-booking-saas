<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'name',
        'description',
        'is_active',
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function packageItems()
    {
        return $this->hasMany(PackageItem::class);
    }

    public function inventoryItems()
    {
        return $this->belongsToMany(InventoryItem::class, 'package_items')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
