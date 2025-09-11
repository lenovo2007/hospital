<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Insumo extends Model
{
    use HasFactory;

    protected $table = 'insumos';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'unidad_medida',
        'cantidad_por_paquete',
        'descripcion',
        'presentacion',
        'status',
    ];
    
    protected $casts = [
        'cantidad_por_paquete' => 'integer',
        'status' => 'string'
    ];
}
