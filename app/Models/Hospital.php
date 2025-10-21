<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\Sede;
use App\Models\User;

class Hospital extends Model
{
    use HasFactory;

    protected $table = 'hospitales';

    protected $fillable = [
        'nombre',
        'nombre_completo',
        'rif',
        'cod_sicm',
        'codigo_alt',
        'email',
        'email_contacto',
        'telefono',
        'nombre_contacto',
        'ubicacion',
        'direccion',
        'dependencia',
        'estado',
        'municipio',
        'parroquia',
        'tipo',
        'status',
    ];

    protected $casts = [
        'ubicacion' => 'array',
        'status' => 'string',
    ];

    /**
     * Get the sedes for the hospital.
     */
    public function sedes()
    {
        return $this->hasMany(Sede::class, 'hospital_id');
    }

    /**
     * Get the users for the hospital.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'hospital_id');
    }

    /**
     * Prepare the model for array/JSON serialization.
     *
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();
        
        // Ensure ubicacion is properly cast to array
        if (isset($array['ubicacion']) && is_string($array['ubicacion'])) {
            $array['ubicacion'] = json_decode($array['ubicacion'], true) ?? [];
        }
        
        return $array;
    }

    /**
     * Get a default hospital instance.
     *
     * @return \App\Models\Hospital
     */
    public static function getDefault()
    {
        return new static([
            'id' => 0,
            'nombre' => 'Hospital Desconocido',
            'status' => 'inactivo',
            'rif' => 'J-00000000-0',
            'direccion' => 'No especificada',
            'telefono' => 'No especificado',
            'email' => 'no-email@example.com',
        ]);
    }

    /**
     * Generar el siguiente código alternativo único
     *
     * @return string
     */
    public static function generarCodigoAlt(): string
    {
        $ultimo = self::whereNotNull('codigo_alt')
            ->orderByDesc('codigo_alt')
            ->value('codigo_alt');

        if (!$ultimo) {
            return 'HOSP-0001';
        }

        // Extraer número del último código (formato: HOSP-0001)
        if (preg_match('/HOSP-(\d+)/', $ultimo, $matches)) {
            $ultimoNumero = (int) $matches[1];
            $nuevoNumero = $ultimoNumero + 1;
            return 'HOSP-' . str_pad($nuevoNumero, 4, '0', STR_PAD_LEFT);
        }

        return 'HOSP-0001';
    }

    /**
     * Boot method to auto-generate codigo_alt
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($hospital) {
            if (empty($hospital->codigo_alt)) {
                $hospital->codigo_alt = self::generarCodigoAlt();
            }
        });
    }
}
