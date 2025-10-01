<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    protected $table = 'movimientos_stock';

    protected $fillable = [
        'tipo',
        'tipo_movimiento',
        'hospital_id',
        'sede_id',
        'origen_almacen_tipo',
        'origen_almacen_id',
        'destino_almacen_tipo',
        'destino_almacen_id',
        'cantidad',
        'fecha_despacho',
        'observaciones',
        'fecha_recepcion',
        'observaciones_recepcion',
        'estado',
        'codigo_grupo',
        'user_id',
        'user_id_receptor',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'fecha_despacho' => 'datetime',
        'fecha_recepcion' => 'datetime',
    ];
}
