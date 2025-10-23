<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'origin_id',
        'destination_id', 
        'shipping_line_id',
        'distance_km',
        'is_deleted'
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'distance_km' => 'decimal:2'
    ];

    // Relationships
    public function origin()
    {
        return $this->belongsTo(Port::class, 'origin_id');
    }

    public function destination()
    {
        return $this->belongsTo(Port::class, 'destination_id');
    }

    public function shippingLine()
    {
        return $this->belongsTo(ShippingLine::class);
    }
}