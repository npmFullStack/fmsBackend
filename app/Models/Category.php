<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'base_rate', 'is_deleted'];

    // Only return records that are not deleted
    protected static function booted()
    {
        static::addGlobalScope('not_deleted', function ($query) {
            $query->where('is_deleted', 0);
        });
    }
}
