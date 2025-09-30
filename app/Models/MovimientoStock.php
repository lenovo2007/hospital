<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    protected $table = 'movimientos_stock';

    protected $fillable = [
        'tipo',
        'tipo_movimiento',
        'lote_id',
        'hospital_id',
        'origen_almacen_tipo',
        'origen_almacen_id',
        'destino_almacen_tipo',
        'destino_almacen_id',
        'cantidad',
        'fecha_despacho',
        'estado',
        'user_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'fecha_despacho' => 'datetime',
    ];
}
