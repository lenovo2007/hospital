<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoHospitalDistribucion extends Model
{
    protected $table = 'tipos_hospital_distribuciones';

    protected $fillable = [
        'tipo1',
        'tipo2',
        'tipo3',
        'tipo4',
    ];

    protected $casts = [
        'tipo1' => 'decimal:18',
        'tipo2' => 'decimal:18',
        'tipo3' => 'decimal:18',
        'tipo4' => 'decimal:18',
    ];
}
