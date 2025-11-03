<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'contact_number',
        'shipper_first_name',
        'shipper_last_name',
        'shipper_contact',
        'consignee_first_name',
        'consignee_last_name',
        'consignee_contact',
        'mode_of_service',
        'container_size_id',
        'container_quantity',
        'origin_id',
        'destination_id',
        'shipping_line_id',
        'departure_date',
        'delivery_date',
        'terms', // Added terms field
        'pickup_location',
        'delivery_location',
        'booking_status',
        'status',
        'is_deleted'
    ];

    protected $casts = [
        'pickup_location' => 'array',
        'delivery_location' => 'array',
        'departure_date' => 'date',
        'delivery_date' => 'date',
        'container_quantity' => 'integer',
        'terms' => 'integer', // Added terms cast
        'is_deleted' => 'boolean'
    ];

    // Relationships
    public function containerSize()
    {
        return $this->belongsTo(ContainerType::class, 'container_size_id');
    }

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
        return $this->belongsTo(ShippingLine::class, 'shipping_line_id');
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    // Scopes
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // Calculate total weight from items
    public function getTotalWeightAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->weight * $item->quantity;
        });
    }
}