<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlmacenCentral extends Model
{
    use HasFactory;

    protected $table = 'almacenes_centrales';

    protected $fillable = [
        'nombre',
    ];
}
