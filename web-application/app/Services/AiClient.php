<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class AiClient
{
    public function predict(UploadedFile $image, array $data): array
    {
        return Http::withToken(config('services.bananashield.token'), 'X-AI-Token')
            ->timeout(10)
            ->retry(1, 150)
            ->attach('image', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
            ->post(config('services.bananashield.url').'/api/v1/predict', [
                'screening_path' => $data['screening_path'],
                'view_type' => $data['view_type'],
            ])->throw()->json();
    }
}
