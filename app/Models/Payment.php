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
        'gcash_mobile_number',
        'gcash_receipt',
        'gcash_transaction_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
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
    public function scopeNotDeleted($query)
    {
        return $query->whereHas('booking', function($q) {
            $q->where('is_deleted', false);
        });
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Generate reference number
    public static function generateReferenceNumber()
    {
        do {
            $number = 'PAY' . strtoupper(\Illuminate\Support\Str::random(3)) . rand(1000, 9999);
        } while (self::where('reference_number', $number)->exists());

        return $number;
    }

    // Check if payment is successful
    public function getIsSuccessfulAttribute()
    {
        return $this->status === 'completed';
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