<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'name',
        'weight',
        'quantity',
        'category'
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'quantity' => 'integer'
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}