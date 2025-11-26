<?php
// app/Models/GeneratedReport.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GeneratedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type_id',
        'title',
        'period_start',
        'period_end',
        'summary_data',
        'key_results',
        'challenges',
        'recommendations',
        'file_path',
        'generated_by',
        'is_sent_to_drse',
        'is_sent_to_cac',
        'is_sent_to_bianco'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'is_sent_to_drse' => 'boolean',
        'is_sent_to_cac' => 'boolean',
        'is_sent_to_bianco' => 'boolean'
    ];

    public function reportType()
    {
        return $this->belongsTo(ReportType::class, 'report_type_id', 'id');
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}