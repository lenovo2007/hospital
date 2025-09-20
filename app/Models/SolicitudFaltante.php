<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudFaltante extends Model
{
    protected $table = 'solicitudes_faltantes';

    protected $fillable = [
        'hospital_id',
        'almacen_tipo',
        'almacen_id',
        'insumo_id',
        'cantidad_sugerida',
        'prioridad',
        'estado',
        'user_id',
        'comentario',
    ];

    protected $casts = [
        'cantidad_sugerida' => 'integer',
    ];
}
