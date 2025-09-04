<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    use HasFactory;

    protected $table = 'hospitales';

    protected $fillable = [
        'nombre',
        'nombre_completo',
        'rif',
        'cod_sicm',
        'email',
        'email_contacto',
        'telefono',
        'nombre_contacto',
        'ubicacion',
        'direccion',
        'tipo',
        'status',
    ];

    protected $casts = [
        'ubicacion' => 'array',
    ];
}
