<?php
// app/Models/AuditSignalement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditSignalement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_dossier',
        'timestamp',
        'type_anonymat',
        'adresse_ip',
        'geolocalisation',
        'identite',
        'telephone',
        'email',
        'region_province',
        'type_fraude',
        'statut',
        'details_supplementaires'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'details_supplementaires' => 'array',
    ];
}