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
        'voucher_number',
        'is_paid',
        'is_deleted'
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'is_deleted' => 'boolean'
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

    // Generate unique voucher number
    public static function generateVoucherNumber()
    {
        do {
            $voucher = Str::upper(Str::random(5));
        } while (self::where('voucher_number', $voucher)->exists());

        return $voucher;
    }

    // Calculate total amount
    public function getTotalAmountAttribute()
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

        return $total;
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