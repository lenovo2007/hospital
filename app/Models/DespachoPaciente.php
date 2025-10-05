<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Hospital;
use App\Models\Sede;
use App\Models\User;
use App\Models\LoteGrupo;

class DespachoPaciente extends Model
{
    use HasFactory;

    protected $table = 'despachos_pacientes';

    protected $fillable = [
        'hospital_id',
        'sede_id',
        'almacen_tipo',
        'fecha_despacho',
        'observaciones',
        'paciente_nombres',
        'paciente_apellidos',
        'paciente_cedula',
        'paciente_telefono',
        'paciente_direccion',
        'medico_tratante',
        'diagnostico',
        'indicaciones_medicas',
        'codigo_despacho',
        'cantidad_total_items',
        'valor_total',
        'estado',
        'fecha_entrega',
        'user_id',
        'user_id_entrega',
        'status',
    ];

    protected $casts = [
        'fecha_despacho' => 'date',
        'fecha_entrega' => 'datetime',
        'valor_total' => 'decimal:2',
        'cantidad_total_items' => 'integer',
        'status' => 'boolean',
    ];

    // Relaciones
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

    public function usuarioEntrega(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_entrega');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(LoteGrupo::class, 'codigo', 'codigo_despacho');
    }

    // Métodos auxiliares
    public function getNombreCompletoAttribute(): string
    {
        return $this->paciente_nombres . ' ' . $this->paciente_apellidos;
    }

    public function getEsEntregadoAttribute(): bool
    {
        return $this->estado === 'entregado';
    }

    public function getEsCanceladoAttribute(): bool
    {
        return $this->estado === 'cancelado';
    }

    // Nota: Ahora se usa el mismo código que lotes_grupos (cod001, cod002, etc.)
    // El método generarCodigoDespacho() ya no se usa
    /*
    public static function generarCodigoDespacho(): string
    {
        $fecha = now()->format('Ymd');
        $ultimo = self::whereDate('created_at', now()->toDateString())
            ->where('codigo_despacho', 'like', "DESP-{$fecha}-%")
            ->count();
        
        $numero = str_pad($ultimo + 1, 3, '0', STR_PAD_LEFT);
        return "DESP-{$fecha}-{$numero}";
    }
    */
}
