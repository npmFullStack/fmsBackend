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
        return $this->hasOne(ApFreightCharge::class, 'ap_id')->where('is_deleted', false);
    }

    public function truckingCharges()
    {
        return $this->hasMany(ApTruckingCharge::class, 'ap_id')->where('is_deleted', false);
    }

    public function portCharges()
    {
        return $this->hasMany(ApPortCharge::class, 'ap_id')->where('is_deleted', false);
    }

    public function miscCharges()
    {
        return $this->hasMany(ApMiscCharge::class, 'ap_id')->where('is_deleted', false);
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

    // New scope for payable charges (has at least one unpaid charge)
    public function scopeHasUnpaidCharges($query)
    {
        return $query->where(function($q) {
            $q->whereHas('freightCharge', function($q) {
                $q->where('is_paid', false);
            })
            ->orWhereHas('truckingCharges', function($q) {
                $q->where('is_paid', false);
            })
            ->orWhereHas('portCharges', function($q) {
                $q->where('is_paid', false);
            })
            ->orWhereHas('miscCharges', function($q) {
                $q->where('is_paid', false);
            });
        });
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

    // New accessor for unpaid charges count
    public function getUnpaidChargesCountAttribute()
    {
        $count = 0;

        if ($this->freightCharge && !$this->freightCharge->is_paid) {
            $count++;
        }

        if ($this->truckingCharges) {
            $count += $this->truckingCharges->where('is_paid', false)->count();
        }

        if ($this->portCharges) {
            $count += $this->portCharges->where('is_paid', false)->count();
        }

        if ($this->miscCharges) {
            $count += $this->miscCharges->where('is_paid', false)->count();
        }

        return $count;
    }

    // New accessor for total unpaid amount
    public function getTotalUnpaidAmountAttribute()
    {
        $total = 0;

        if ($this->freightCharge && !$this->freightCharge->is_paid) {
            $total += $this->freightCharge->amount;
        }

        if ($this->truckingCharges) {
            $total += $this->truckingCharges->where('is_paid', false)->sum('amount');
        }

        if ($this->portCharges) {
            $total += $this->portCharges->where('is_paid', false)->sum('amount');
        }

        if ($this->miscCharges) {
            $total += $this->miscCharges->where('is_paid', false)->sum('amount');
        }

        return $total;
    }
}