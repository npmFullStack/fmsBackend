<?php
// [file name]: ApFreightCharge.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApFreightCharge extends Model
{
    use HasFactory;

    protected $table = 'ap_freight_charges';

    protected $fillable = [
        'ap_id',
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