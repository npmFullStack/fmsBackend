<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'user_id',
        'payment_method',
        'gcash_receipt_image',
        'reference_number',
        'amount',
        'status',
        'admin_notes',
        'payment_date',
        'verified_at',
        'approved_at',
        'rejected_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime'
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
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCod($query)
    {
        return $query->where('payment_method', 'cod');
    }

    public function scopeGcash($query)
    {
        return $query->where('payment_method', 'gcash');
    }

    // Status check methods
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isVerified()
    {
        return $this->status === 'verified';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    // Actions

    public function markAsApproved($notes = null)
    {
        return $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes
        ]);
    }

    public function markAsRejected($notes = null)
    {
        return $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes
        ]);
    }

    // Helper methods
public function getReceiptUrl()
{
    if ($this->gcash_receipt_image) {
        // Check if it's already a full URL
        if (filter_var($this->gcash_receipt_image, FILTER_VALIDATE_URL)) {
            return $this->gcash_receipt_image;
        }
        // Otherwise, generate the storage URL
        return asset('storage/' . $this->gcash_receipt_image);
    }
    return null;
}

    public function getStatusBadge()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'verified' => 'bg-blue-100 text-blue-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
        ];
        
        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getPaymentMethodText()
    {
        return $this->payment_method === 'cod' 
            ? 'Cash on Delivery' 
            : 'GCash';
    }
}