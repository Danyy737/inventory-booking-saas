<?php

namespace App\Models;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_organisation_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Organisations this user belongs to (multi-tenant memberships).
     * Pivot table: organisation_user (includes 'role').
     */
    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The organisation the user is currently operating in.
     */
    public function currentOrganisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'current_organisation_id');
    }
}
