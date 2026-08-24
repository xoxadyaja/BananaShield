<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_area_hectares' => 'decimal:2',
            'notification_preferences' => 'array',
        ];
    }

    public function sections()
    {
        return $this->hasMany(FarmSection::class)->orderBy('name');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'managed_by');
    }
}
