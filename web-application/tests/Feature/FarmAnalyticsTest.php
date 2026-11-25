<?php

namespace Tests\Feature;

use App\Models\CaseImage;
use App\Models\FarmProfile;
use App\Models\FarmSection;
use App\Models\PlantCase;
use App\Models\Prediction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function farm(User $owner, string $name): FarmProfile
    {
        return FarmProfile::create([
            'farm_name' => $name, 'municipality' => 'Bansalan',
            'province' => 'Davao del Sur', 'managed_by' => $owner->id,
        ]);
    }

    private function block(FarmProfile $farm, string $name): FarmSection
    {
        return FarmSection::create([
            'farm_profile_id' => $farm->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    private function report(User $monitor, ?FarmProfile $farm, ?string $class, string $date, string $decision = 'conclusive', ?string $block = 'Block A'): PlantCase
    {
        $case = PlantCase::create([
            'case_number' => 'BS-TEST-'.uniqid(),
            'submitted_by' => $monitor->id,
            'farm_profile_id' => $farm?->id,
            'farm_section' => $block,
            'screening_path' => 'auto_detected',
            'observed_at' => $date,
            'created_at' => $date.' 12:00:00',
            'status' => 'open',
            'review_status' => 'pending',
        ]);
        if ($class !== null) {
            $image = CaseImage::create([
                'case_id' => $case->id,
                'view_type' => 'auto_detected', 'image_type' => 'original',
                'storage_disk' => 'local', 'storage_path' => 'tests/screening.png',
                'original_filename' => 'screening.png', 'mime_type' => 'image/png',
                'file_size' => 100, 'width' => 300, 'height' => 300,
                'image_quality_status' => 'accepted', 'metadata_removed' => false,
                'uploaded_at' => now(),
            ]);
            Prediction::create([
                'case_id' => $case->id, 'image_id' => $image->id,
                'predicted_class' => $class, 'display_label' => $class,
                'confidence' => 0.89, 'decision_status' => $decision,
                'quality_status' => 'accepted', 'quality_flags' => [],
                'result_message' => 'Preliminary screening.', 'disclaimer' => 'Preliminary result only.',
            ]);
        }
        return $case;
    }

    public function test_analytics_counts_latest_results_and_filters_every_section_by_farm(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $owner = User::factory()->create(['role' => 'farm_owner', 'status' => 'active']);
        $monitor = User::factory()->create(['role' => 'monitoring_personnel', 'status' => 'active']);
        $firstFarm = $this->farm($owner, 'Alpha Farm');
        $secondFarm = $this->farm($owner, 'Beta Farm');
        $this->farm($owner, 'Empty Farm');
        $healthy = $this->report($monitor, $firstFarm, 'healthy_banana', '2026-09-05');
        $changed = $this->report($monitor, $firstFarm, 'healthy_banana', '2026-08-05');
        $newPrediction = $changed->latestPrediction->replicate();
        $newPrediction->predicted_class = 'black_sigatoka';
        $newPrediction->display_label = 'Black Sigatoka';
        $newPrediction->save();
        // A recorded inconclusive decision takes precedence over its proposed class.
        $this->report($monitor, $secondFarm, 'healthy_banana', '2026-09-05', 'inconclusive');
        $legacyHealthy = $this->report($monitor, null, 'healthy_banana', '2026-01-05');
        $this->report($monitor, $secondFarm, null, '2026-09-05');

        $response = $this->actingAs($owner)->get('/analytics')->assertOk();
        $analytics = $response->viewData('analytics');
        $this->assertSame(['total' => 5, 'healthy' => 2, 'disease' => 1, 'inconclusive' => 1, 'unavailable' => 1], $analytics['summary']);
        $this->assertSame(4, array_sum(array_column($analytics['trend'], 'total')));
        $this->assertSame(5, array_sum(array_column($analytics['classes'], 'value')));
        $this->assertSame(5, $analytics['farmRows']->sum('total'));
        $this->assertSame(4, $analytics['farmRows']->count());
        $this->assertSame(0, $analytics['farmRows']->firstWhere('name', 'Empty Farm')['total']);
        $this->assertSame(5, $analytics['statuses']['open']);
        $this->assertSame(5, $analytics['pendingReview']);
        $this->get($analytics['metrics'][1]['href'])->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 2);
        $this->get("/cases/{$legacyHealthy->id}")->assertOk();

        $filtered = $this->get('/analytics?farm='.$firstFarm->id)->assertOk()->viewData('analytics');
        $this->assertSame(['total' => 2, 'healthy' => 1, 'disease' => 1, 'inconclusive' => 0, 'unavailable' => 0], $filtered['summary']);
        $this->assertSame(2, array_sum(array_column($filtered['trend'], 'total')));
        $this->assertSame(2, array_sum(array_column($filtered['classes'], 'value')));
        $this->assertSame(2, $filtered['statuses']['open']);
        $this->assertSame(2, $filtered['pendingReview']);
        $this->assertCount(1, $filtered['farmRows']);
        $this->get($filtered['metrics'][1]['href'])->assertOk()
            ->assertViewHas('selectedFarm', (string) $firstFarm->id)
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 1 && $cases->first()->id === $healthy->id);
        $this->get('/monitoring?farm='.$secondFarm->id.'&outcome=inconclusive')->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 1);
        $this->get('/monitoring?farm='.$secondFarm->id.'&outcome=unavailable')->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 1);
        $this->get('/analytics?farm=__unassigned')->assertOk()
            ->assertViewHas('analytics', fn ($data) => $data['summary']['total'] === 1 && $data['summary']['healthy'] === 1);
        $this->get('/monitoring?farm=__unassigned')->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 1 && $cases->first()->id === $legacyHealthy->id);
    }

    public function test_empty_farms_and_invalid_filters_are_handled(): void
    {
        $owner = User::factory()->create(['role' => 'farm_owner', 'status' => 'active']);
        $farm = $this->farm($owner, 'No Screenings Farm');
        $this->actingAs($owner)->get('/analytics?farm='.$farm->id)->assertOk()
            ->assertSee('No screenings recorded for this farm yet')
            ->assertViewHas('analytics', fn ($data) =>
                $data['summary'] === ['total' => 0, 'healthy' => 0, 'disease' => 0, 'inconclusive' => 0, 'unavailable' => 0]
                && count($data['trend']) === 6
            );
        $this->getJson('/analytics?farm=999999')->assertUnprocessable()->assertJsonValidationErrors('farm');
        $this->getJson('/monitoring?farm=999999')->assertUnprocessable()->assertJsonValidationErrors('farm');
        $this->getJson('/monitoring?outcome=invalid')->assertUnprocessable()->assertJsonValidationErrors('outcome');
    }

    public function test_block_analytics_finds_tied_hotspots_and_uses_only_latest_predictions(): void
    {
        $owner = User::factory()->create(['role' => 'farm_owner', 'status' => 'active']);
        $monitor = User::factory()->create(['role' => 'monitoring_personnel', 'status' => 'active']);
        $alpha = $this->farm($owner, 'Alpha Farm');
        $beta = $this->farm($owner, 'Beta Farm');
        $this->block($alpha, 'North Block');
        $this->block($alpha, 'South Block');
        $this->block($alpha, 'Zero Case Block');
        $this->block($beta, 'Orchard Block');

        $this->report($monitor, $alpha, 'black_sigatoka', '2026-09-01', 'conclusive', 'North Block');
        $this->report($monitor, $alpha, 'black_sigatoka', '2026-09-02', 'conclusive', 'North Block');
        $this->report($monitor, $alpha, 'fusarium_wilt', '2026-09-03', 'conclusive', 'North Block');
        $reclassified = $this->report($monitor, $alpha, 'black_sigatoka', '2026-09-04', 'conclusive', 'North Block');
        $latest = $reclassified->latestPrediction->replicate();
        $latest->predicted_class = 'healthy_banana';
        $latest->display_label = 'Healthy Banana';
        $latest->save();

        $this->report($monitor, $alpha, 'fusarium_wilt', '2026-09-05', 'conclusive', 'South Block');
        $this->report($monitor, $alpha, 'fusarium_wilt', '2026-09-06', 'conclusive', 'South Block');
        $this->report($monitor, $alpha, 'banana_bunchy_top_disease', '2026-09-07', 'conclusive', 'South Block');
        $this->report($monitor, $alpha, 'black_sigatoka', '2026-09-08', 'conclusive', null);
        // The trained-model artifact labels this class as bbtv; imported results use it too.
        $this->report($monitor, $beta, 'bbtv', '2026-09-09', 'conclusive', 'Orchard Block');

        $analytics = $this->actingAs($owner)->get('/analytics')->assertOk()->viewData('analytics');
        $alphaAnalytics = $analytics['blockAnalytics']->firstWhere('id', $alpha->id);
        $north = $alphaAnalytics['blocks']->firstWhere('name', 'North Block');
        $south = $alphaAnalytics['blocks']->firstWhere('name', 'South Block');
        $zeroCase = $alphaAnalytics['blocks']->firstWhere('name', 'Zero Case Block');
        $unassigned = $alphaAnalytics['blocks']->firstWhere('name', 'Unassigned Block');

        $this->assertSame(4, $north['total']);
        $this->assertSame(3, $north['disease']);
        $this->assertSame(2, $north['black_sigatoka']);
        $this->assertSame(1, $north['fusarium_wilt']);
        $this->assertSame(1, $north['healthy']);
        $this->assertSame('Black Sigatoka', $north['dominant_disease']);
        $this->assertSame(3, $south['disease']);
        $this->assertSame('Fusarium Wilt', $south['dominant_disease']);
        $this->assertSame(0, $zeroCase['total']);
        $this->assertSame('None', $zeroCase['dominant_disease']);
        $this->assertSame(1, $unassigned['disease']);
        $this->assertSame(['North Block', 'South Block'], $alphaAnalytics['hotspot']['blocks']);
        $this->assertSame(['Black Sigatoka', 'Fusarium Wilt'], $alphaAnalytics['hotspot']['diseases']);

        $betaAnalytics = $analytics['blockAnalytics']->firstWhere('id', $beta->id);
        $this->assertSame(1, $betaAnalytics['blocks']->firstWhere('name', 'Orchard Block')['banana_bunchy_top_disease']);
        $this->assertSame(1, $betaAnalytics['hotspot']['count']);
        $this->assertSame(['Banana Bunchy Top Disease'], $betaAnalytics['hotspot']['diseases']);
        $this->assertSame('block-disease-chart-'.$alpha->id, $alphaAnalytics['chart_id']);
        $this->get($north['href'])->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 4);
    }

    public function test_block_analytics_keeps_zero_disease_farms_and_restricts_non_owners(): void
    {
        $owner = User::factory()->create(['role' => 'farm_owner', 'status' => 'active']);
        $monitor = User::factory()->create(['role' => 'monitoring_personnel', 'status' => 'active']);
        $farm = $this->farm($owner, 'Healthy Farm');
        $this->block($farm, 'Healthy Block');
        $this->block($farm, 'No Reports Block');
        $this->report($monitor, $farm, 'healthy_banana', '2026-09-05', 'conclusive', 'Healthy Block');

        $analytics = $this->actingAs($owner)->get('/analytics?farm='.$farm->id)->assertOk()->viewData('analytics');
        $farmAnalytics = $analytics['blockAnalytics']->sole();
        $this->assertFalse($farmAnalytics['hotspot']['has_disease']);
        $this->assertSame([], $farmAnalytics['hotspot']['blocks']);
        $this->assertSame(0, $farmAnalytics['blocks']->firstWhere('name', 'No Reports Block')['total']);
        $this->assertSame('None', $farmAnalytics['blocks']->firstWhere('name', 'Healthy Block')['dominant_disease']);

        $this->actingAs($monitor)->get('/analytics')->assertForbidden();
    }

    public function test_report_pagination_preserves_farm_and_outcome_filters(): void
    {
        $owner = User::factory()->create(['role' => 'farm_owner', 'status' => 'active']);
        $monitor = User::factory()->create(['role' => 'monitoring_personnel', 'status' => 'active']);
        $farm = $this->farm($owner, 'Paginated Farm');
        for ($index = 0; $index < 13; $index++) {
            $this->report($monitor, $farm, 'healthy_banana', '2026-09-05');
        }
        $otherFarm = $this->farm($owner, 'Separate Farm');
        $this->report($monitor, $otherFarm, 'healthy_banana', '2026-09-05');
        $this->actingAs($owner)->get('/monitoring?farm='.$farm->id.'&outcome=healthy&page=2')
            ->assertOk()->assertViewHas('cases', function ($cases) use ($farm) {
                return $cases->total() === 13 && $cases->count() === 1
                    && $cases->first()->farm_profile_id === $farm->id
                    && str_contains($cases->url(1), 'farm='.$farm->id)
                    && str_contains($cases->url(1), 'outcome=healthy');
            });
    }
}
