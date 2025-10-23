<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'weight',
        'base_price',
        'calculated_price',
        'is_deleted'
    ];

    protected $casts = [
        'is_deleted' => 'boolean'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}