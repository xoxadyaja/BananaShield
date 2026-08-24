<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelVersion extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['active' => 'boolean', 'confidence_threshold' => 'float', 'metrics_summary' => 'array']; }
}
