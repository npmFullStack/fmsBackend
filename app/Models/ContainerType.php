<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContainerType extends Model
{
    use HasFactory;

    protected $fillable = [
        'size',
        'load_type',
        'max_weight',
        'fcl_rate',
        'is_deleted'
    ];
}
