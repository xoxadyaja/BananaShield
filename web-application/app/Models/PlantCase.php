<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlantCase extends Model
{
    protected $table = 'cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'observed_at' => 'date',
            'reviewed_at' => 'datetime',
            'referred_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function farmProfile() { return $this->belongsTo(FarmProfile::class); }
    public function images() { return $this->hasMany(CaseImage::class, 'case_id'); }
    public function predictions() { return $this->hasMany(Prediction::class, 'case_id'); }
    public function latestPrediction() { return $this->hasOne(Prediction::class, 'case_id')->latestOfMany(); }
    public function followUps() { return $this->hasMany(FollowUp::class, 'case_id')->latest(); }

    public function scopeReportable(Builder $query): Builder
    {
        // Every completed screening is reportable, including healthy results.
        return $query;
    }

    public function scopeOutcome(Builder $query, string $outcome): Builder
    {
        return $query->where(function (Builder $cases) use ($outcome) {
            $cases->whereHas('latestPrediction', fn (Builder $prediction) =>
                $prediction->whereRaw(Prediction::outcomeSql().' = ?', [$outcome])
            );
            if ($outcome === 'unavailable') {
                $cases->orWhereDoesntHave('latestPrediction');
            }
        });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->reportable();

        return $user->role === 'monitoring_personnel'
            ? $query->where('submitted_by', $user->id)
            : $query;
    }

    public function isReportable(): bool
    {
        return true;
    }
}
