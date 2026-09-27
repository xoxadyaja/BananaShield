<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MockPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PartDetectionFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function monitor(): User
    {
        return User::factory()->create([
            'role' => 'monitoring_personnel',
            'status' => 'active',
        ]);
    }

    private function image(string $name = 'plant.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAASwAAAEsCAIAAAD2HxkiAAADWUlEQVR4nO3VMREAIBDEwAc1KEEdopGRZtfAVZlb590BOjvcBkQIPU8IMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECNP6FaEDUuPql+UAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    private function prediction(): array
    {
        return [
            'success' => true,
            'screening_path' => 'auto_detected',
            'view_type' => 'auto_detected',
            'predicted_class' => 'black_sigatoka',
            'display_label' => 'Black Sigatoka',
            'decision_status' => 'conclusive',
            'confidence' => 0.91,
            'confidence_threshold' => 0.75,
            'architecture' => 'EfficientNet-B0 test integration',
            'model_version' => 'test-v1',
            'inference_time_ms' => 120,
            'quality_status' => 'accepted',
            'quality_flags' => [],
            'message' => 'Deterministic test screening result.',
            'disclaimer' => 'Preliminary visual-screening result only.',
        ];
    }

    public function test_screening_page_exposes_automatic_detection_states(): void
    {
        $this->actingAs($this->monitor())->get('/screenings/new')
            ->assertOk()
            ->assertSee('Analyzing image...')
            ->assertSee('Detected image view')
            ->assertSee('Capture another image')
            ->assertSee('Choose another image')
            ->assertSee('part_detection_receipt', false);
    }

    public function test_valid_detection_returns_a_signed_receipt(): void
    {
        config([
            'services.bananashield.part_detection_mode' => 'mock',
            'services.bananashield.part_detection_mock_scenario' => 'Whole Plant',
        ]);

        $response = $this->actingAs($this->monitor())->postJson('/screenings/detect-part', [
            'image' => $this->image(),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_banana_image', true)
            ->assertJsonPath('usable', true)
            ->assertJsonPath('part', 'Whole Plant')
            ->assertJsonStructure(['detection_receipt']);
    }

    public function test_invalid_banana_image_never_runs_disease_classification(): void
    {
        config([
            'services.bananashield.mode' => 'mock',
            'services.bananashield.part_detection_mode' => 'mock',
            'services.bananashield.part_detection_mock_scenario' => 'invalid_image',
        ]);
        $this->mock(MockPredictionService::class, fn ($mock) => $mock->shouldNotReceive('predict'));

        $this->actingAs($this->monitor())->from('/screenings/new')->post('/screenings', [
            'image' => $this->image('dog.png'),
            'observed_at' => '2026-09-24',
        ])->assertRedirect('/screenings/new')->assertSessionHasErrors('image');

        $this->assertDatabaseCount('cases', 0);
        $this->assertDatabaseCount('predictions', 0);
    }

    public function test_valid_receipt_allows_efficientnet_and_persists_detected_part(): void
    {
        config([
            'services.bananashield.mode' => 'mock',
            'services.bananashield.part_detection_mode' => 'mock',
            'services.bananashield.part_detection_mock_scenario' => 'Pseudostem',
        ]);
        $monitor = $this->monitor();
        $receipt = $this->actingAs($monitor)->postJson('/screenings/detect-part', [
            'image' => $this->image(),
        ])->assertOk()->json('detection_receipt');

        $this->mock(MockPredictionService::class, function ($mock) {
            $mock->shouldReceive('predict')->once()->andReturn($this->prediction());
        });

        $this->actingAs($monitor)->post('/screenings', [
            'image' => $this->image(),
            'part_detection_receipt' => $receipt,
            'observed_at' => '2026-09-24',
        ])->assertOk()->assertSee('Detected image view')->assertSee('Pseudostem');

        $this->assertDatabaseHas('case_images', [
            'detected_part' => 'Pseudostem',
            'part_detection_provider' => 'Gemini',
            'part_detection_status' => 'valid',
        ]);
        $this->assertDatabaseCount('predictions', 1);
    }
}
