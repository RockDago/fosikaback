<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enseignant extends Model
{
    use HasFactory;

    protected $fillable = [
        'universite_id',
        'etablissement_id',
        'nom',
        'sexe',
        'im',
        'date_naissance',
        'corps',
        'diplome',
        'specialite',
        'categorie'
    ];

    protected $casts = [
        'date_naissance' => 'date'
    ];

    public function universite()
    {
        return $this->belongsTo(Universite::class);
    }

    public function etablissement()
    {
        return $this->belongsTo(Etablissement::class);
    }
}
