<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FichaInsumo extends Model
{
    use HasFactory;

    protected $table = 'ficha_insumos';

    protected $fillable = [
        'hospital_id',
        'insumo_id',
        'cantidad',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'cantidad' => 'integer',
    ];

    /**
     * Relación con Hospital
     */
    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Relación con Insumo
     */
    public function insumo()
    {
        return $this->belongsTo(Insumo::class);
    }
}
