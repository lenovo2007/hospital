<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\AlmacenPrincipal;
use App\Models\LoteAlmacen;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class StockService
{
    /**
     * Incrementa stock para un lote en un almacén.
     */
    public function incrementar(int $loteId, string $almacenTipo, int $almacenId, int $cantidad, int $hospitalId, ?int $sedeId = null): LoteAlmacen
    {
        return DB::transaction(function () use ($loteId, $almacenTipo, $almacenId, $cantidad, $hospitalId, $sedeId) {
            $registro = LoteAlmacen::firstOrNew([
                'lote_id' => $loteId,
                'almacen_tipo' => $almacenTipo,
                'almacen_id' => $almacenId,
            ]);
            $registro->hospital_id = $hospitalId;
            if ($sedeId !== null) {
                $registro->sede_id = $sedeId;
            }
            $registro->cantidad = max(0, (int) $registro->cantidad + $cantidad);
            $registro->ultima_actualizacion = now();
            $registro->save();
            return $registro;
        });
    }

    /**
     * Disminuye stock para un lote en un almacén. Lanza excepción si no hay suficiente.
     */
    public function disminuir(int $loteId, string $almacenTipo, int $almacenId, int $cantidad): LoteAlmacen
    {
        return DB::transaction(function () use ($loteId, $almacenTipo, $almacenId, $cantidad) {
            $registro = LoteAlmacen::where('lote_id', $loteId)
                ->where('almacen_tipo', $almacenTipo)
                ->where('almacen_id', $almacenId)
                ->lockForUpdate()
                ->first();
            if (!$registro) {
                throw new StockException('Stock no encontrado para este lote y almacén.');
            }
            if ($registro->cantidad < $cantidad) {
                throw new StockException('Stock insuficiente. Disponible: ' . $registro->cantidad);
            }
            $registro->cantidad = (int) $registro->cantidad - $cantidad;
            $registro->ultima_actualizacion = now();
            $registro->save();
            return $registro;
        });
    }

    /**
     * Transfiere stock de un almacén a otro.
     */
    public function transferir(int $loteId, string $origenTipo, int $origenId, string $destinoTipo, int $destinoId, int $cantidad, int $hospitalIdDestino, ?int $sedeDestinoId = null): void
    {
        DB::transaction(function () use ($loteId, $origenTipo, $origenId, $destinoTipo, $destinoId, $cantidad, $hospitalIdDestino, $sedeDestinoId) {
            $this->disminuir($loteId, $origenTipo, $origenId, $cantidad);
            $this->incrementar($loteId, $destinoTipo, $destinoId, $cantidad, $hospitalIdDestino, $sedeDestinoId);

            if ($destinoTipo === 'almacenPrin') {
                $registroPrincipal = AlmacenPrincipal::firstOrNew([
                    'sede_id' => $sedeDestinoId,
                    'lote_id' => $loteId,
                    'hospital_id' => $hospitalIdDestino,
                ]);
                $registroPrincipal->cantidad = max(0, (int) $registroPrincipal->cantidad + $cantidad);
                $registroPrincipal->status = $registroPrincipal->status ?? true;
                $registroPrincipal->save();
            }
        });
    }
}
