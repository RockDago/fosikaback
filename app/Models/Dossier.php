<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Dossier extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'titre',
        'description',
        'type',
        'statut',
        'priorite',
        'citoyen_id',
        'agent_id',
        'investigateur_id',
        'date_creation',
        'date_cloture',
        'lieu',
        'metadata',
    ];

    protected $casts = [
        'date_creation' => 'datetime',
        'date_cloture' => 'datetime',
        'metadata' => 'array',
        'statut' => 'boolean',
    ];

    protected $appends = ['statut_label', 'priorite_color'];

    // Relations
    public function citoyen()
    {
        return $this->belongsTo(User::class, 'citoyen_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function investigateur()
    {
        return $this->belongsTo(User::class, 'investigateur_id');
    }

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }

    // Attributs calculés
    public function getStatutLabelAttribute()
    {
        $statuts = [
            true => 'Actif',
            false => 'Clôturé',
        ];
        
        return $statuts[$this->statut] ?? 'Inconnu';
    }

    public function getPrioriteColorAttribute()
    {
        $colors = [
            'basse' => 'gray',
            'normale' => 'blue',
            'elevee' => 'orange',
            'urgente' => 'red',
        ];
        
        return $colors[$this->priorite] ?? 'blue';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('statut', true);
    }

    public function scopeForCitoyen($query, $userId)
    {
        return $query->where('citoyen_id', $userId);
    }

    // Méthodes utilitaires
    public static function generateReference()
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));
        return "REF-{$date}-{$random}";
    }

    public function getInfoForChat()
    {
        return [
            'reference' => $this->reference,
            'titre' => $this->titre,
            'type' => $this->type,
            'statut' => $this->statut_label,
            'priorite' => $this->priorite,
            'date_creation' => $this->date_creation->format('d/m/Y'),
        ];
    }
}