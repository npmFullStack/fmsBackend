<?php
// app/Models/Quote.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
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
        'truck_comp_id',
        'terms',
        'pickup_location',
        'delivery_location',
        'total_amount',
        'charges',
        'sent_at',
        'status',
        'is_deleted'
    ];

    protected $casts = [
        'pickup_location' => 'array',
        'delivery_location' => 'array',
        'charges' => 'array',
        'total_amount' => 'decimal:2',
        'container_quantity' => 'integer',
        'terms' => 'integer',
        'is_deleted' => 'boolean',
        'sent_at' => 'datetime'
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

    public function truckComp()
    {
        return $this->belongsTo(TruckComp::class, 'truck_comp_id');
    }

    public function items()
    {
        return $this->hasMany(QuoteItem::class);
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

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    // Check if quote has been sent
    public function getIsSentAttribute()
    {
        return $this->status === 'sent' && !is_null($this->sent_at);
    }

    // Get formatted total amount
    public function getFormattedTotalAttribute()
    {
        return $this->total_amount ? '$' . number_format($this->total_amount, 2) : 'Not quoted';
    }
}