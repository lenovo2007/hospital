<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedes';

    protected $fillable = [
        'nombre',
        'tipo',
        'hospital_id',
        'status',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }
}
