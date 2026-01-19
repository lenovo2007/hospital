<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Solicitud extends Model
{
    use HasFactory;

    protected $table = 'solicitudes';

    protected $fillable = [
        'tipo_solicitud',
        'descripcion',
        'prioridad',
        'fecha',
        'sede_id',
        'hospital_id',
        'status',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    /**
     * Get the hospital that owns the solicitud.
     */
    public function hospital()
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }

    /**
     * Get the sede that owns the solicitud.
     */
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

}
