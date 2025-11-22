<?php
// app/Models/QuoteItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'name',
        'weight',
        'quantity',
        'category'
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'quantity' => 'integer'
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }
}