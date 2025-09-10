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
    protected $appends = ['can_crud_user', 'hospital', 'sede'];

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
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }

    /**
     * Get the sede that owns the user.
     */
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * Get the hospital attribute.
     *
     * @return array|null
     */
    public function getHospitalAttribute()
    {
        // Do not trigger DB loads here; only use already-loaded relation
        $hospital = $this->relationLoaded('hospital') ? $this->getRelation('hospital') : null;
        
        return $hospital ? [
            'id' => $hospital->id,
            'nombre' => $hospital->nombre,
            'rif' => $hospital->rif,
            'direccion' => $hospital->direccion,
            'telefono' => $hospital->telefono,
            'email' => $hospital->email
        ] : null;
    }

    /**
     * Get the sede attribute.
     *
     * @return array|null
     */
    public function getSedeAttribute()
    {
        // Do not trigger DB loads here; only use already-loaded relation
        $sede = $this->relationLoaded('sede') ? $this->getRelation('sede') : null;
        
        return $sede ? [
            'id' => $sede->id,
            'nombre' => $sede->nombre,
            'direccion' => $sede->direccion,
            'telefono' => $sede->telefono,
            'email' => $sede->email,
            'hospital_id' => $sede->hospital_id
        ] : null;
    }
}