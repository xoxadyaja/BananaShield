<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiseaseClass extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['active' => 'boolean']; }
    public function advisories() { return $this->hasMany(Advisory::class, 'disease_id'); }
    public function activeAdvisory() { return $this->hasOne(Advisory::class, 'disease_id')->where('active', true)->latestOfMany(); }
}
