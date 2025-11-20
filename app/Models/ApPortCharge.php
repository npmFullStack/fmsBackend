<?php
// [file name]: ApPortCharge.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApPortCharge extends Model
{
    use HasFactory;

    protected $table = 'ap_port_charges';

    protected $fillable = [
        'ap_id',
        'voucher_number', // Added voucher_number
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

    public function accountsPayable()
    {
        return $this->belongsTo(AccountsPayable::class, 'ap_id');
    }
}