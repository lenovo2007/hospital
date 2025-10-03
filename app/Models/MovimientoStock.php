<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoStock extends Model
{
    protected $table = 'movimientos_stock';

    protected $fillable = [
        'tipo',
        'tipo_movimiento',
        'origen_hospital_id',
        'origen_sede_id',
        'destino_hospital_id',
        'destino_sede_id',
        'origen_almacen_tipo',
        'origen_almacen_id',
        'destino_almacen_tipo',
        'destino_almacen_id',
        'cantidad_salida_total',
        'cantidad_entrada_total',
        'discrepancia_total',
        'fecha_despacho',
        'observaciones',
        'fecha_recepcion',
        'observaciones_recepcion',
        'estado',
        'codigo_grupo',
        'user_id',
        'user_id_receptor',
    ];

    protected $casts = [
        'cantidad_salida_total' => 'integer',
        'cantidad_entrada_total' => 'integer',
        'discrepancia_total' => 'boolean',
        'fecha_despacho' => 'datetime',
        'fecha_recepcion' => 'datetime',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'destino_hospital_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'destino_sede_id');
    }

    public function destinoHospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'destino_hospital_id');
    }

    public function destinoSede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'destino_sede_id');
    }

    public function origenHospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'origen_hospital_id');
    }

    public function origenSede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'origen_sede_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usuarioReceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_receptor');
    }

    public function seguimientos()
    {
        return $this->hasMany(Seguimiento::class, 'movimiento_stock_id');
    }
}
