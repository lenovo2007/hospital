<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlmacenServiciosApoyo extends Model
{
    use HasFactory;

    protected $table = 'almacenes_servicios_apoyo';

    protected $fillable = [
        'insumos',
        'codigo',
        'numero_lote',
        'fecha_vencimiento',
        'fecha_ingreso',
        'cantidad',
        'status',
    ];
}
