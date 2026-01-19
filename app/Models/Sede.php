<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedes';

    protected $fillable = [
        'nombre',
        'tipo_almacen',
        'hospital_id',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the hospital that owns the sede.
     */
    public function hospital()
    {
        try {
            return $this->belongsTo(Hospital::class, 'hospital_id')->withDefault(function($hospital, $sede) {
                Log::warning("Sede #{$sede->id} has invalid hospital_id: {$sede->hospital_id}");
                return new \App\Models\Hospital([
                    'id' => $sede->hospital_id,
                    'nombre' => 'Hospital Desconocido',
                    'status' => 'inactivo'
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Error loading hospital for sede #{$this->id}: " . $e->getMessage());
            return $this->belongsTo(Hospital::class, 'hospital_id')->withDefault();
        }
    }

    /**
     * Get the users for the sede.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'sede_id');
    }

    /**
     * Prepare the model for array/JSON serialization.
     *
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();
        
        // Ensure hospital is included in the array if loaded
        if ($this->relationLoaded('hospital') && $this->hospital) {
            $array['hospital'] = $this->hospital->only([
                'id',
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
                'created_at',
                'updated_at'
            ]);
        }
        
        return $array;
    }
}
