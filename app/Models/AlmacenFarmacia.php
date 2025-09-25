<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlmacenFarmacia extends Model
{
    use HasFactory;

    protected $table = 'almacenes_farmacia';

    protected $fillable = [
        'cantidad',
        'sede_id',
        'lote_id',
        'hospital_id',
        'status',
    ];
}
