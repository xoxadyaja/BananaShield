<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class AiClient
{
    public function detectPart(UploadedFile $image): array
    {
        return $this->request()
            ->attach('file', file_get_contents($image->getRealPath()), $this->safeFilename($image))
            ->post(config('services.bananashield.url').'/detect-part')
            ->throw()
            ->json();
    }

    public function predict(UploadedFile $image, array $data): array
    {
        return $this->request()
            ->attach('image', file_get_contents($image->getRealPath()), $this->safeFilename($image))
            ->post(config('services.bananashield.url').'/api/v1/predict', [
                'analysis_mode' => 'automatic',
            ])->throw()->json();
    }

    private function request()
    {
        return Http::withHeaders([
            'X-AI-Token' => (string) config('services.bananashield.token'),
            'Accept' => 'application/json',
        ])->timeout((int) config('services.bananashield.timeout', 15))
            ->retry(2, 200);
    }

    private function safeFilename(UploadedFile $image): string
    {
        $extension = match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };

        return 'bananashield-upload.'.$extension;
    }
}
