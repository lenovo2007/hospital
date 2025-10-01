<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoDiscrepancia extends Model
{
    protected $table = 'movimientos_discrepancias';

    protected $fillable = [
        'movimiento_stock_id',
        'lote_id',
        'cantidad_esperada',
        'cantidad_recibida',
        'observaciones',
    ];
}
