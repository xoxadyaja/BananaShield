<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmSection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'area_hectares' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function farmProfile()
    {
        return $this->belongsTo(FarmProfile::class);
    }
}
