<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class MockPredictionService
{
    private const CLASSES = [
        ['healthy_banana', 'Healthy Banana', 'No supported visible disease pattern was identified in this image.'],
        ['black_sigatoka', 'Black Sigatoka', 'The visible symptoms are consistent with Black Sigatoka.'],
        ['fusarium_wilt', 'Fusarium Wilt', 'The visible symptoms are consistent with Fusarium Wilt.'],
        ['banana_bunchy_top_disease', 'Banana Bunchy Top Disease', 'The visible symptoms are consistent with Banana Bunchy Top Disease.'],
    ];

    public function predict(UploadedFile $image, string $path, string $viewType): array
    {
        if (random_int(1, 10) === 1) {
            return $this->result($path, $viewType, null, 0.43, 'inconclusive');
        }

        $class = self::CLASSES[array_rand(self::CLASSES)];
        return $this->result($path, $viewType, $class, random_int(78, 91) / 100, 'conclusive');
    }

    private function result(string $path, string $viewType, ?array $class, float $confidence, string $status): array
    {
        return [
            'success' => true,
            'screening_path' => $path,
            'view_type' => $viewType,
            'predicted_class' => $class[0] ?? 'inconclusive',
            'display_label' => $class[1] ?? 'Inconclusive result',
            'decision_status' => $status,
            'confidence' => $confidence,
            'confidence_threshold' => 0.75,
            'architecture' => 'EfficientNet-B0 integration (mock output)',
            'model_version' => 'efficientnet-b0-demo-v0.2',
            'inference_time_ms' => 420,
            'quality_status' => 'accepted',
            'quality_flags' => $status === 'inconclusive' ? ['insufficient_confidence'] : [],
            'message' => $class[2] ?? 'We could not produce a sufficiently reliable preliminary result from this image.',
            'disclaimer' => 'This is a preliminary visual-screening result and not a confirmed diagnosis.',
            'mock_fallback' => true,
        ];
    }
}
