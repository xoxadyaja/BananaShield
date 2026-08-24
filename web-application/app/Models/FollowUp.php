<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUp extends Model
{
    protected $guarded = [];
    public function plantCase() { return $this->belongsTo(PlantCase::class, 'case_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
