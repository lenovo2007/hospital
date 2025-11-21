<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Solicitud extends Model
{
    use HasFactory;

    protected $table = 'solicitudes';

    protected $fillable = [
        'codigo',
        'hospital_id',
        'sede_id',
        'insumo_id',
        'cantidad',
        'estado',
        'descripcion',
        'observaciones',
        'user_id',
        'fecha_solicitud',
        'fecha_aprobacion',
        'fecha_entrega',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_entrega' => 'datetime',
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

    /**
     * Get the insumo that owns the solicitud.
     */
    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    /**
     * Get the user that owns the solicitud.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
