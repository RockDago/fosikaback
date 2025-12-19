<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Etablissement extends Model
{
    use HasFactory;

    protected $fillable = [
        'universite_id',
        'nom'
    ];

    public function universite()
    {
        return $this->belongsTo(Universite::class);
    }

    public function enseignants()
    {
        return $this->hasMany(Enseignant::class);
    }
}
