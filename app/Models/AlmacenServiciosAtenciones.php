<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlmacenServiciosAtenciones extends Model
{
    use HasFactory;

    protected $table = 'almacenes_servicios_atenciones';

    protected $fillable = [
        'cantidad',
        'sede_id',
        'lote_id',
        'hospital_id',
        'status',
    ];
}
