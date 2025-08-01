<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'cobrador_id',
        'name',
        'description',
    ];

    /**
     * Get the cobrador that manages this route.
     */
    public function cobrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cobrador_id');
    }

    /**
     * Get the clients that belong to this route.
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_routes', 'route_id', 'client_id')
                    ->withTimestamps();
    }
} 