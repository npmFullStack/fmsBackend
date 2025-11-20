<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CargoMonitoring extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'pending_at',
        'picked_up_at',
        'origin_port_at',
        'in_transit_at',
        'destination_port_at',
        'out_for_delivery_at',
        'delivered_at',
        'current_status'
    ];

    protected $casts = [
        'pending_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'origin_port_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'destination_port_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // Status update methods
    public function markAsPickedUp()
    {
        return $this->update([
            'picked_up_at' => now(),
            'current_status' => 'Picked Up'
        ]);
    }

    public function markAsOriginPort()
    {
        return $this->update([
            'origin_port_at' => now(),
            'current_status' => 'Origin Port'
        ]);
    }

    public function markAsInTransit()
    {
        return $this->update([
            'in_transit_at' => now(),
            'current_status' => 'In Transit'
        ]);
    }

    public function markAsDestinationPort()
    {
        return $this->update([
            'destination_port_at' => now(),
            'current_status' => 'Destination Port'
        ]);
    }

    public function markAsOutForDelivery()
    {
        return $this->update([
            'out_for_delivery_at' => now(),
            'current_status' => 'Out for Delivery'
        ]);
    }

    public function markAsDelivered()
    {
        return $this->update([
            'delivered_at' => now(),
            'current_status' => 'Delivered'
        ]);
    }
}