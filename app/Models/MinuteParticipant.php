<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinuteParticipant extends Model
{
    public $timestamps = false;

    protected $fillable = ['orden', 'nombre', 'puesto', 'asistio', 'modalidad'];

    protected function casts(): array
    {
        return ['asistio' => 'boolean'];
    }
}
