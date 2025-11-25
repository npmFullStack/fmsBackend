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
        'provider_payment_id',
        'provider_checkout_url',
        'provider_response',
        'customer_email',
        'customer_name',
        'customer_phone',
        'checkout_created_at',
        'paid_at',
        'failed_at',
        'description',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'provider_response' => 'array',
        'metadata' => 'array',
        'checkout_created_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
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
            $number = 'PAY' . date('Ymd') . rand(1000, 9999);
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

    // Mark as completed
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
            'payment_date' => now(),
        ]);
    }

    // Mark as failed
    public function markAsFailed()
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
        ]);
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (!$payment->reference_number) {
                $payment->reference_number = self::generateReferenceNumber();
            }
            
            // Set customer information from user
            if ($payment->user && !$payment->customer_email) {
                $payment->customer_email = $payment->user->email;
                $payment->customer_name = $payment->user->first_name . ' ' . $payment->user->last_name;
                $payment->customer_phone = $payment->user->contact_number;
            }
        });
    }
}