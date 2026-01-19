<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngresoDirecto extends Model
{
    use HasFactory;

    protected $table = 'ingresos_directos';

    protected $fillable = [
        'codigo_ingreso',
        'tipo_ingreso',
        'fecha_ingreso',
        'hospital_id',
        'sede_id',
        'almacen_tipo',
        'proveedor_nombre',
        'proveedor_rif',
        'numero_factura',
        'valor_total',
        'observaciones',
        'motivo',
        'cantidad_total_items',
        'estado',
        'codigo_lotes_grupo',
        'user_id',
        'fecha_procesado',
        'user_id_procesado',
        'status',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_procesado' => 'datetime',
        'valor_total' => 'decimal:2',
        'cantidad_total_items' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * Relación con Hospital
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Relación con Sede
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Relación con Usuario que registra
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con Usuario que procesa
     */
    public function usuarioProcesado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_procesado');
    }

    /**
     * Obtener los lotes asociados a este ingreso
     */
    public function lotesGrupo()
    {
        return LoteGrupo::where('codigo', $this->codigo_lotes_grupo)->get();
    }

    /**
     * Generar código único de ingreso
     */
    public static function generarCodigoIngreso(): string
    {
        $fecha = now()->format('Ymd');
        $ultimo = self::whereDate('created_at', now()->toDateString())
            ->where('codigo_ingreso', 'like', "ING-{$fecha}-%")
            ->count();
        
        $numero = str_pad($ultimo + 1, 3, '0', STR_PAD_LEFT);
        return "ING-{$fecha}-{$numero}";
    }

    /**
     * Scopes
     */
    public function scopeActivo($query)
    {
        return $query->where('status', true);
    }

    public function scopePorSede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo_ingreso', $tipo);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Accessors
     */
    public function getEsProcesadoAttribute(): bool
    {
        return $this->estado === 'procesado';
    }

    public function getEsCanceladoAttribute(): bool
    {
        return $this->estado === 'cancelado';
    }

    public function getTipoIngresoFormateadoAttribute(): string
    {
        return match($this->tipo_ingreso) {
            'donacion' => 'Donación',
            'compra' => 'Compra',
            'ajuste_inventario' => 'Ajuste de Inventario',
            'devolucion' => 'Devolución',
            'otro' => 'Otro',
            default => $this->tipo_ingreso
        };
    }
}
