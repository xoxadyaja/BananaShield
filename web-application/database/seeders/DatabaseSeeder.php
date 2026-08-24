<?php

namespace Database\Seeders;

use App\Models\Advisory;
use App\Models\DiseaseClass;
use App\Models\FarmProfile;
use App\Models\ModelVersion;
use App\Models\User;
use App\Services\PrototypeContentService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Farm Owner', 'email' => 'owner@bananashield.local', 'role' => 'farm_owner'],
            ['name' => 'Monitoring Personnel', 'email' => 'monitor@bananashield.local', 'role' => 'monitoring_personnel'],
            ['name' => 'System Administrator', 'email' => 'admin@bananashield.local', 'role' => 'system_administrator'],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(['email' => $account['email']], $account + [
                'password' => env('BANANASHIELD_SEED_PASSWORD', 'ChangeMe!2026'),
                'status' => 'active',
            ]);
        }

        $owner = User::where('email', 'owner@bananashield.local')->first();
        FarmProfile::firstOrCreate([], [
            'farm_name' => 'BananaShield Farm',
            'municipality' => 'Selected Municipality',
            'province' => 'Davao del Sur',
            'notification_email' => $owner?->email,
            'notification_preferences' => [
                'case_updates' => true,
                'referral_alerts' => true,
                'weekly_summary' => false,
            ],
            'managed_by' => $owner?->id,
        ]);

        ModelVersion::updateOrCreate(['version_name' => 'efficientnet-b0-demo-v0.2'], [
            'screening_path' => 'all',
            'architecture' => 'EfficientNet-B0 integration (mock output)',
            'confidence_threshold' => 0.75,
            'metrics_summary' => null,
            'model_size' => null,
            'inference_summary' => 'Integration registry only. Replace with a trained and independently evaluated model before AI_MODE=model.',
            'active' => true,
        ]);

        $content = app(PrototypeContentService::class)->advisories();
        $descriptions = [
            'healthy_banana' => 'No supported visible disease pattern is identified; this does not prove that the plant is disease-free.',
            'black_sigatoka' => 'A supported category for preliminary screening of visible Black Sigatoka patterns.',
            'fusarium_wilt' => 'A supported category for preliminary screening of visible Fusarium Wilt patterns.',
            'banana_bunchy_top_disease' => 'A supported category for preliminary screening of visible Banana Bunchy Top Disease patterns.',
        ];

        foreach ($descriptions as $technicalName => $description) {
            $disease = DiseaseClass::updateOrCreate(['technical_name' => $technicalName], [
                'display_name' => $content[$technicalName]['title'],
                'screening_path' => 'all',
                'description' => $description,
                'active' => true,
            ]);
            $item = $content[$technicalName];
            Advisory::updateOrCreate(['disease_id' => $disease->id, 'version_label' => 'v1'], [
                'language' => 'en',
                'visible_signs' => implode("\n", $item['signs']),
                'prevention' => implode("\n", $item['prevention']),
                'containment_reminders' => implode("\n", $item['containment']),
                'general_guidance' => implode("\n", $item['guidance']),
                'consultation_guidance' => $item['consult'],
                'reviewed_by' => null,
                'active' => true,
            ]);
        }
    }
}
