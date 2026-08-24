<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseImage extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['uploaded_at' => 'datetime', 'metadata_removed' => 'boolean']; }
    public function plantCase() { return $this->belongsTo(PlantCase::class, 'case_id'); }
    public function prediction() { return $this->hasOne(Prediction::class, 'image_id'); }
}
