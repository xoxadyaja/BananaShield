<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class PartDetectionService
{
    private const ALLOWED_PARTS = [
        'Leaf',
        'Pseudostem',
        'Crown / Upper Leaves',
        'Whole Plant',
        'Unknown',
    ];

    public function __construct(private readonly AiClient $ai) {}

    public function detect(UploadedFile $image): array
    {
        $raw = config('services.bananashield.part_detection_mode') === 'mock'
            ? $this->mockResult()
            : $this->ai->detectPart($image);

        if (($raw['success'] ?? null) !== true) {
            throw new RuntimeException('The plant-part service did not complete successfully.');
        }

        $part = $raw['part'] ?? null;
        if (! is_string($part) || ! in_array($part, self::ALLOWED_PARTS, true)) {
            throw new RuntimeException('The plant-part service returned an unsupported category.');
        }

        $isBanana = ($raw['is_banana_image'] ?? null) === true;
        $usable = ($raw['usable'] ?? null) === true;
        if (! $isBanana) {
            $part = 'Unknown';
            $usable = false;
        }
        if ($part === 'Unknown') {
            $usable = false;
        }

        $status = $usable
            ? 'valid'
            : ($isBanana ? 'unclear_image' : 'invalid_image');

        $fallbackMessage = match ($status) {
            'valid' => "The primary banana plant view is {$part}.",
            'invalid_image' => 'The submitted image does not appear to contain a valid banana plant.',
            default => 'A banana plant may be present, but the image is too unclear to determine the plant part.',
        };

        return [
            'success' => true,
            'is_banana_image' => $isBanana,
            'part' => $part,
            'usable' => $usable,
            'message' => Str::limit(strip_tags((string) ($raw['message'] ?? $fallbackMessage)), 500, ''),
            'provider' => 'Gemini',
            'status' => $status,
        ];
    }

    private function mockResult(): array
    {
        $scenario = (string) config('services.bananashield.part_detection_mock_scenario', 'Leaf');
        if ($scenario === 'invalid_image') {
            return [
                'success' => true,
                'is_banana_image' => false,
                'part' => 'Unknown',
                'usable' => false,
                'message' => 'The submitted image does not appear to contain a valid banana plant.',
            ];
        }
        if ($scenario === 'unclear_image') {
            return [
                'success' => true,
                'is_banana_image' => true,
                'part' => 'Unknown',
                'usable' => false,
                'message' => 'A banana plant may be present, but the image is too unclear to determine the plant part.',
            ];
        }

        $part = in_array($scenario, array_slice(self::ALLOWED_PARTS, 0, 4), true) ? $scenario : 'Leaf';

        return [
            'success' => true,
            'is_banana_image' => true,
            'part' => $part,
            'usable' => true,
            'message' => "The primary banana plant view is {$part}.",
        ];
    }
}
