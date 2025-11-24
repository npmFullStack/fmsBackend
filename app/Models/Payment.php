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
        'gcash_mobile_number',
        'gcash_receipt',
        'gcash_transaction_id',
        'paymongo_payment_intent_id',
        'paymongo_source_id',
        'paymongo_response',
        'paymongo_checkout_url',
        'paymongo_status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'paymongo_response' => 'array',
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

    public function accountsReceivable()
    {
        return $this->belongsTo(AccountsReceivable::class, 'booking_id', 'booking_id');
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

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
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

    // Check if payment is pending
    public function getIsPendingAttribute()
    {
        return $this->status === 'pending';
    }

    // Check if payment can be processed
    public function getCanProcessAttribute()
    {
        return in_array($this->status, ['pending', 'processing']);
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