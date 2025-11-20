<?php
// [file name]: ApMiscCharge.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApMiscCharge extends Model
{
    use HasFactory;

    protected $table = 'ap_misc_charges';

    protected $fillable = [
        'ap_id',
        'voucher_number',
        'charge_type',
        'payee',
        'amount',
        'check_date',
        'voucher',
        'is_paid',
        'is_deleted'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'check_date' => 'date',
        'is_paid' => 'boolean',
        'is_deleted' => 'boolean'
    ];

    // Scopes
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function accountsPayable()
    {
        return $this->belongsTo(AccountsPayable::class, 'ap_id');
    }
}