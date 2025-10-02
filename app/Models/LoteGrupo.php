<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoteGrupo extends Model
{
    use HasFactory;

    protected $table = 'lotes_grupos';

    protected $fillable = [
        'codigo',
        'lote_id',
        'cantidad_salida',
        'cantidad_entrada',
        'discrepancia',
        'status',
    ];

    protected $casts = [
        'cantidad_salida' => 'integer',
        'cantidad_entrada' => 'integer',
        'discrepancia' => 'boolean',
    ];

    /**
     * Relación con el modelo Lote
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    /**
     * Generar el siguiente código único
     */
    public static function generarCodigo(): string
    {
        $ultimo = self::latest('id')->first();

        if (!$ultimo) {
            return 'cod001';
        }

        // Extraer número del último código
        $ultimoNumero = (int) substr($ultimo->codigo, 3);
        $nuevoNumero = $ultimoNumero + 1;

        return 'cod' . str_pad($nuevoNumero, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Crear grupo de items para un movimiento
     */
    public static function crearGrupo(array $items): array
    {
        $codigo = self::generarCodigo();
        $grupoItems = [];

        foreach ($items as $item) {
            $grupoItem = self::create([
                'codigo' => $codigo,
                'lote_id' => $item['lote_id'],
                'cantidad_salida' => $item['cantidad'],
                'cantidad_entrada' => 0,
                'discrepancia' => false,
                'status' => 'activo',
            ]);

            $grupoItems[] = $grupoItem;
        }

        return [$codigo, $grupoItems];
    }

    /**
     * Obtener items por código de grupo
     */
    public static function obtenerPorCodigo(string $codigo): array
    {
        return self::where('codigo', $codigo)
            ->where('status', 'activo')
            ->with('lote')
            ->get()
            ->toArray();
    }

    /**
     * Scope para filtrar por status
     */
    public function scopeActivo($query)
    {
        return $query->where('status', 'activo');
    }

    public function scopeInactivo($query)
    {
        return $query->where('status', 'inactivo');
    }
}
