<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoHospitalDistribucion extends Model
{
    protected $table = 'tipos_hospital_distribuciones';

    protected $fillable = [
        'tipo',
        'porcentaje',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
    ];
}
