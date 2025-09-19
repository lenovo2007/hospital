<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoteAlmacen extends Model
{
    protected $table = 'lotes_almacenes';

    protected $fillable = [
        'lote_id',
        'almacen_id',
        'cantidad',
        'ultima_actualizacion',
        'hospital_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'ultima_actualizacion' => 'datetime',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }
}
