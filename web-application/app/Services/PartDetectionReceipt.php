<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class PartDetectionReceipt
{
    private const LIFETIME_SECONDS = 600;

    public function issue(UploadedFile $image, array $result): string
    {
        return Crypt::encryptString(json_encode([
            'image_sha256' => hash_file('sha256', $image->getRealPath()),
            'expires_at' => now()->addSeconds(self::LIFETIME_SECONDS)->timestamp,
            'result' => $result,
        ], JSON_THROW_ON_ERROR));
    }

    public function verify(UploadedFile $image, string $receipt): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($receipt), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            throw new RuntimeException('The image analysis receipt is invalid. Please analyze the image again.', previous: $exception);
        }

        if (($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('The image analysis has expired. Please analyze the image again.');
        }

        $expectedHash = (string) ($payload['image_sha256'] ?? '');
        $actualHash = hash_file('sha256', $image->getRealPath());
        if (! $expectedHash || ! hash_equals($expectedHash, $actualHash)) {
            throw new RuntimeException('The selected image changed after analysis. Please analyze it again.');
        }

        $result = $payload['result'] ?? null;
        if (! is_array($result) || ($result['success'] ?? null) !== true) {
            throw new RuntimeException('The image analysis receipt is incomplete. Please analyze the image again.');
        }

        return $result;
    }
}
