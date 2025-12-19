<?php
// app/Models/AuditSysteme.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditSysteme extends Model
{
    use HasFactory;

    protected $fillable = [
        'timestamp',
        'utilisateur',
        'action',
        'entite',
        'statut',
        'ip',
        'details',
        'reference_dossier',
        'metadata'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'metadata' => 'array',
    ];
}