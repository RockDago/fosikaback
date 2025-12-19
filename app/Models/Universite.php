<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Universite extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'province',
        'code'
    ];

    public function etablissements()
    {
        return $this->hasMany(Etablissement::class);
    }

    public function enseignants()
    {
        return $this->hasMany(Enseignant::class);
    }
}
