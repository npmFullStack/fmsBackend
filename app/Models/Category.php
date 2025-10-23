<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'base_rate', 
        'weight_multiplier', 
        'surcharge_percentage', 
        'is_deleted'
    ];

    protected $casts = [
        'is_deleted' => 'boolean'
    ];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}