<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id', 
        'weight',
        'base_freight_cost',
        'total_cost',
        'is_deleted'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}