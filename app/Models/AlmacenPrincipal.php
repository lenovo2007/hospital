<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlmacenPrincipal extends Model
{
    use HasFactory;

    protected $table = 'almacenes_principales';

    protected $fillable = [
        'nombre',
        'status',
    ];
}
