<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Advisory extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['active' => 'boolean']; }
    public function disease() { return $this->belongsTo(DiseaseClass::class, 'disease_id'); }
}
