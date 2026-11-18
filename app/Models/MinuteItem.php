<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Renglón de «3. Puntos tratados» (tipo punto) o «4. Acuerdos» (tipo acuerdo). */
class MinuteItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['tipo', 'orden', 'texto'];
}
