<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seguimiento extends Model
{
    use HasFactory;

    protected $table = 'seguimientos';

    protected $fillable = [
        'movimiento_stock_id',
        'ubicacion',
        'estado',
        'status',
        'observaciones',
        'user_id_repartidor',
    ];

    protected $casts = [
        'ubicacion' => 'array',
        'movimiento_stock_id' => 'integer',
        'user_id_repartidor' => 'integer',
    ];

    /**
     * Relación con MovimientoStock
     */
    public function movimientoStock(): BelongsTo
    {
        return $this->belongsTo(MovimientoStock::class, 'movimiento_stock_id');
    }

    /**
     * Relación con User (repartidor)
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_repartidor');
    }

    /**
     * Crear un nuevo registro de seguimiento
     */
    public static function crearSeguimiento(
        int $movimientoStockId,
        string $estado,
        int $userIdRepartidor,
        ?array $ubicacion = null,
        ?string $observaciones = null
    ): self {
        return self::create([
            'movimiento_stock_id' => $movimientoStockId,
            'ubicacion' => $ubicacion,
            'estado' => $estado,
            'status' => 'activo',
            'observaciones' => $observaciones,
            'user_id_repartidor' => $userIdRepartidor,
        ]);
    }

    /**
     * Obtener el último seguimiento de un movimiento
     */
    public static function ultimoSeguimiento(int $movimientoStockId): ?self
    {
        return self::where('movimiento_stock_id', $movimientoStockId)
            ->orderByDesc('created_at')
            ->first();
    }
}
