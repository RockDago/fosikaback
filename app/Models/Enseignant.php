<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enseignant extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'enseignants';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
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

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date_naissance' => 'date',
        'universite_id' => 'integer',
        'etablissement_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Relation avec le modèle Universite
     */
    public function universite()
    {
        return $this->belongsTo(Universite::class);
    }

    /**
     * Relation avec le modèle Etablissement
     */
    public function etablissement()
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Scope pour filtrer par université
     */
    public function scopeByUniversite($query, $universiteId)
    {
        return $query->where('universite_id', $universiteId);
    }

    /**
     * Scope pour filtrer par établissement
     */
    public function scopeByEtablissement($query, $etablissementId)
    {
        return $query->where('etablissement_id', $etablissementId);
    }

    /**
     * Scope pour filtrer par corps
     */
    public function scopeByCorps($query, $corps)
    {
        return $query->where('corps', $corps);
    }

    /**
     * Scope pour filtrer par catégorie
     */
    public function scopeByCategorie($query, $categorie)
    {
        return $query->where('categorie', $categorie);
    }

    /**
     * Scope pour filtrer par sexe
     */
    public function scopeBySexe($query, $sexe)
    {
        return $query->where('sexe', $sexe);
    }

    /**
     * Scope pour recherche multi-champs
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('nom', 'LIKE', "%{$search}%")
              ->orWhere('im', 'LIKE', "%{$search}%")
              ->orWhere('diplome', 'LIKE', "%{$search}%")
              ->orWhere('specialite', 'LIKE', "%{$search}%")
              ->orWhere('corps', 'LIKE', "%{$search}%")
              ->orWhere('categorie', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Accessor pour obtenir le nom complet formaté
     */
    public function getNomCompletAttribute()
    {
        return trim($this->nom);
    }

    /**
     * Accessor pour obtenir l'âge calculé
     */
    public function getAgeAttribute()
    {
        return $this->date_naissance ? now()->diffInYears($this->date_naissance) : null;
    }

    /**
     * Boot method pour les événements du modèle
     */
    protected static function boot()
    {
        parent::boot();

        // Avant la création, normaliser les données
        static::creating(function ($enseignant) {
            $enseignant->nom = trim($enseignant->nom);
            $enseignant->im = trim($enseignant->im);
            $enseignant->diplome = trim($enseignant->diplome ?? '');
            $enseignant->specialite = trim($enseignant->specialite ?? '');
        });

        // Avant la mise à jour, normaliser les données
        static::updating(function ($enseignant) {
            if ($enseignant->isDirty('nom')) {
                $enseignant->nom = trim($enseignant->nom);
            }
            if ($enseignant->isDirty('im')) {
                $enseignant->im = trim($enseignant->im);
            }
            if ($enseignant->isDirty('diplome')) {
                $enseignant->diplome = trim($enseignant->diplome ?? '');
            }
            if ($enseignant->isDirty('specialite')) {
                $enseignant->specialite = trim($enseignant->specialite ?? '');
            }
        });
    }
}
