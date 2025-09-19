<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lote extends Model
{
    protected $table = 'lotes';

    protected $fillable = [
        'id_insumo',
        'numero_lote',
        'fecha_vencimiento',
        'fecha_ingreso',
        'hospital_id'
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date:Y-m-d',
        'fecha_ingreso' => 'date:Y-m-d',
    ];

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'id_insumo');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(LoteAlmacen::class, 'lote_id');
    }
}
