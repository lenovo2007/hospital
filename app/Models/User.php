<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
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
    protected $with = ['hospital', 'sede'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_reset_expires_at' => 'datetime',
            'is_root' => 'boolean',
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
            'can_crud_user' => 'boolean',
            'status' => 'string',
        ];
    }

    // Relaciones
    public function hospital()
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id'); // sedes table
    }

    /**
     * Get the can_crud_user attribute.
     *
     * @return bool
     */
    /**
     * Get the can_crud_user attribute.
     *
     * @return bool
     */
    public function getCanCrudUserAttribute($value)
    {
        return (bool) $value;
    }
    
    /**
     * Get the hospital relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getHospitalAttribute()
    {
        return $this->hospital()->first();
    }
    
    /**
     * Get the sede relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getSedeAttribute()
    {
        return $this->sede()->first();
    }
}

