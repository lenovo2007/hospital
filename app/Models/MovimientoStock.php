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
        'hospital_id',
        'sede_id',
        'origen_almacen_tipo',
        'origen_almacen_id',
        'destino_almacen_tipo',
        'destino_almacen_id',
        'cantidad',
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
        'cantidad' => 'integer',
        'fecha_despacho' => 'datetime',
        'fecha_recepcion' => 'datetime',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usuarioReceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_receptor');
    }
}
