<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'base_rate_per_km',
        'weight_rate_per_km', 
        'min_charge',
        'is_deleted'
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'base_rate_per_km' => 'decimal:2',
        'weight_rate_per_km' => 'decimal:4',
        'min_charge' => 'decimal:2'
    ];

    // Relationships
    public function shipRoutes()
    {
        return $this->hasMany(ShipRoute::class);
    }
}