<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    protected $table = 'movimientos_stock';

    protected $fillable = [
        'tipo',
        'lote_id',
        'hospital_id',
        'origen_almacen_tipo',
        'origen_almacen_id',
        'destino_almacen_tipo',
        'destino_almacen_id',
        'cantidad',
        'user_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];
}
