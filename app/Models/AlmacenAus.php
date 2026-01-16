<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlmacenAus extends Model
{
    use HasFactory;

    protected $table = 'almacenes_aus';

    protected $fillable = [
        'insumo_id',
        'cantidad',
        'sede_id',
        'lote_id',
        'hospital_id',
        'estado',
        'status',
    ];

    protected $casts = [
        'insumo_id' => 'integer',
        'status' => 'boolean',
        'cantidad' => 'integer',
        'sede_id' => 'integer',
        'lote_id' => 'integer',
        'hospital_id' => 'integer',
    ];

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class);
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }
}
