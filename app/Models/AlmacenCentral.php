<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlmacenCentral extends Model
{
    use HasFactory;

    protected $table = 'almacenes_centrales';

    protected $fillable = [
        'insumo_id',
        'cantidad',
        'sede_id',
        'lote_id',
        'hospital_id',
        'estado',
        'status',
    ];

    protected $casts = [
        'insumo_id' => 'integer',
        'cantidad' => 'integer',
        'sede_id' => 'integer',
        'lote_id' => 'integer',
        'hospital_id' => 'integer',
        'estado' => 'string',
        'status' => 'boolean',
    ];
}
