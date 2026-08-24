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
    public function images() { return $this->hasMany(CaseImage::class, 'case_id'); }
    public function predictions() { return $this->hasMany(Prediction::class, 'case_id'); }
    public function latestPrediction() { return $this->hasOne(Prediction::class, 'case_id')->latestOfMany(); }
    public function followUps() { return $this->hasMany(FollowUp::class, 'case_id')->latest(); }

    public function scopeReportable(Builder $query): Builder
    {
        return $query->where(function (Builder $caseQuery) {
            $caseQuery
                ->whereDoesntHave('predictions')
                ->orWhereHas('latestPrediction', function (Builder $predictionQuery) {
                    $predictionQuery->where('predicted_class', '!=', 'healthy_banana');
                });
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
        return $this->latestPrediction()->value('predicted_class') !== 'healthy_banana';
    }
}
