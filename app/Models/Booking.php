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
        'container_size',
        'origin',
        'destination',
        'shipping_line',
        'departure_date',
        'delivery_date',
        'pickup_location',
        'delivery_location',
        'items',
        'status',
        'is_deleted'
    ];

    protected $casts = [
        'pickup_location' => 'array',
        'delivery_location' => 'array',
        'items' => 'array',
        'departure_date' => 'date',
        'delivery_date' => 'date',
        'is_deleted' => 'boolean'
    ];
}