<?php
// app/Models/ReportType.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReportType extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'description',
        'icon',
        'is_active'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function generatedReports()
    {
        return $this->hasMany(GeneratedReport::class, 'report_type_id', 'id');
    }
}