<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['confidence' => 'float', 'quality_flags' => 'array']; }
    public function plantCase() { return $this->belongsTo(PlantCase::class, 'case_id'); }
    public function image() { return $this->belongsTo(CaseImage::class, 'image_id'); }
    public function modelVersion() { return $this->belongsTo(ModelVersion::class); }
}
