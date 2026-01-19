<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlmacenServiciosApoyo extends Model
{
    use HasFactory;

    protected $table = 'almacenes_servicios_apoyo';

    protected $fillable = [
        'cantidad',
        'sede_id',
        'lote_id',
        'hospital_id',
        'status',
    ];
}
