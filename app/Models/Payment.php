<?php
// [file name]: Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'payment_method',
        'reference_number',
        'amount',
        'status',
        'payment_date',
        'paymongo_payment_id',
        'paymongo_checkout_url',
        'paymongo_response',
        'paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'paymongo_response' => 'array',
        'paid_at' => 'datetime'
    ];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Generate reference number
    public static function generateReferenceNumber()
    {
        do {
            $number = 'PAY' . date('YmdHis') . rand(100, 999);
        } while (self::where('reference_number', $number)->exists());

        return $number;
    }

    // Check if payment is successful
    public function getIsPaidAttribute()
    {
        return $this->status === 'paid';
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (!$payment->reference_number) {
                $payment->reference_number = self::generateReferenceNumber();
            }
        });
    }
}