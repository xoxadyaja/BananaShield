<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['confidence' => 'float', 'quality_flags' => 'array']; }
    /**
     * Shared outcome categories for analytics and report filters.
     * An inconclusive decision takes precedence over the proposed class.
     */
    public static function outcomeSql(): string
    {
        return "CASE
            WHEN predictions.decision_status = 'inconclusive' OR predictions.predicted_class = 'inconclusive' THEN 'inconclusive'
            WHEN predictions.decision_status = 'conclusive' AND predictions.predicted_class = 'healthy_banana' THEN 'healthy'
            WHEN predictions.decision_status = 'conclusive' AND predictions.predicted_class IN ('black_sigatoka', 'fusarium_wilt', 'banana_bunchy_top_disease') THEN 'disease'
            ELSE 'unavailable'
        END";
    }

    public function plantCase() { return $this->belongsTo(PlantCase::class, 'case_id'); }
    public function image() { return $this->belongsTo(CaseImage::class, 'image_id'); }
    public function modelVersion() { return $this->belongsTo(ModelVersion::class); }
}
