<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoDiscrepancia extends Model
{
    protected $table = 'movimientos_discrepancias';

    protected $fillable = [
        'movimiento_stock_id',
        'codigo_lote_grupo',
        'cantidad_esperada',
        'cantidad_recibida',
        'observaciones',
    ];

    protected $casts = [
        'movimiento_stock_id' => 'integer',
        'cantidad_esperada' => 'integer',
        'cantidad_recibida' => 'integer',
    ];

    /**
     * Relación con MovimientoStock
     */
    public function movimientoStock(): BelongsTo
    {
        return $this->belongsTo(MovimientoStock::class, 'movimiento_stock_id');
    }

    /**
     * Relación con LoteGrupo
     */
    public function loteGrupo(): BelongsTo
    {
        return $this->belongsTo(LoteGrupo::class, 'codigo_lote_grupo', 'codigo');
    }
}
