<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MiniAlmacen extends Model
{
    use HasFactory;

    protected $table = 'mini_almacenes';

    protected $fillable = [
        'nombre',
    ];
}
