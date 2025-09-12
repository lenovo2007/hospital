<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tipo',
        'rol',
        'nombre',
        'apellido',
        'genero',
        'cedula',
        'telefono',
        'direccion',
        'hospital_id',
        'sede_id',
        'can_view',
        'can_create',
        'can_update',
        'can_delete',
        'can_crud_user',
        'email',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'can_crud_user' => 'boolean',
        'is_root' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['can_crud_user'];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [];

    /**
     * Get the can_crud_user attribute.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function getCanCrudUserAttribute($value = null)
    {
        if ($value === null) {
            return (bool) ($this->attributes['can_crud_user'] ?? false);
        }
        return (bool) $value;
    }

    /**
     * Get the hospital that owns the user.
     */
    public function hospital()
    {
        return $this->belongsTo(Hospital::class, 'hospital_id')
            ->withDefault(function() {
                return Hospital::getDefault();
            });
    }

    /**
     * Get the sede that owns the user.
     */
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id')
            ->withDefault(function() {
                return new Sede([
                    'id' => 0,
                    'nombre' => 'Sede Desconocida',
                    'status' => 'inactiva',
                    'tipo_almacen' => 'no_especificado',
                    'hospital_id' => $this->hospital_id ?? 0
                ]);
            });
    }
    
    /**
     * Prepare the model for array/JSON serialization.
     *
     * @return array
     */
    public function toArray()
    {
        // Return only the model attributes (no injected relations) to avoid duplication in API responses
        return parent::toArray();
    }

    /**
     * Get the hospital data for the user.
     *
     * @return array|null
     */
    public function getHospitalDataAttribute()
    {
        if (!$this->relationLoaded('hospital')) {
            $this->load('hospital');
        }
        
        return $this->hospital ? [
            'id' => $this->hospital->id,
            'nombre' => $this->hospital->nombre,
            'rif' => $this->hospital->rif,
            'direccion' => $this->hospital->direccion,
            'telefono' => $this->hospital->telefono,
            'email' => $this->hospital->email,
            'status' => $this->hospital->status
        ] : null;
    }

    /**
     * Get the sede data for the user.
     *
     * @return array|null
     */
    public function getSedeDataAttribute()
    {
        if (!$this->relationLoaded('sede')) {
            $this->load('sede');
        }
        
        return $this->sede ? [
            'id' => $this->sede->id,
            'nombre' => $this->sede->nombre,
            'tipo_almacen' => $this->sede->tipo_almacen,
            'hospital_id' => $this->sede->hospital_id,
            'status' => $this->sede->status
        ] : null;
    }
}