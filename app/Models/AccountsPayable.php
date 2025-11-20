<?php
// [file name]: AccountsPayable.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AccountsPayable extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'is_paid',
        'is_deleted',
        'total_expenses'
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'is_deleted' => 'boolean',
        'total_expenses' => 'decimal:2'
    ];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function freightCharge()
    {
        return $this->hasOne(ApFreightCharge::class, 'ap_id');
    }

    public function truckingCharges()
    {
        return $this->hasMany(ApTruckingCharge::class, 'ap_id');
    }

    public function portCharges()
    {
        return $this->hasMany(ApPortCharge::class, 'ap_id');
    }

    public function miscCharges()
    {
        return $this->hasMany(ApMiscCharge::class, 'ap_id');
    }

    // Scopes
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    // Generate unique voucher number for charges
    public static function generateChargeVoucherNumber($prefix = 'CHG')
    {
        do {
            $voucher = $prefix . strtoupper(Str::random(3)) . rand(100, 999);
        } while (
            ApFreightCharge::where('voucher_number', $voucher)->exists() ||
            ApTruckingCharge::where('voucher_number', $voucher)->exists() ||
            ApPortCharge::where('voucher_number', $voucher)->exists() ||
            ApMiscCharge::where('voucher_number', $voucher)->exists()
        );

        return $voucher;
    }

    // Calculate total amount and update total_expenses
    public function calculateTotalAmount()
    {
        $total = 0;

        // Freight charges
        if ($this->freightCharge) {
            $total += $this->freightCharge->amount;
        }

        // Trucking charges
        $total += $this->truckingCharges->sum('amount');

        // Port charges
        $total += $this->portCharges->sum('amount');

        // Misc charges
        $total += $this->miscCharges->sum('amount');

        // Update the total_expenses field
        $this->update(['total_expenses' => $total]);

        return $total;
    }

    // Accessor for total amount (for backward compatibility)
    public function getTotalAmountAttribute()
    {
        return $this->total_expenses;
    }

    // Check if all charges are paid
    public function getAllChargesPaidAttribute()
    {
        $charges = collect();

        if ($this->freightCharge) {
            $charges->push($this->freightCharge);
        }

        $charges = $charges->merge($this->truckingCharges)
                          ->merge($this->portCharges)
                          ->merge($this->miscCharges);

        return $charges->count() > 0 && $charges->every(function ($charge) {
            return $charge->is_paid;
        });
    }
}