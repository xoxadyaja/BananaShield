<?php

namespace Tests\Feature;

use App\Models\CaseImage;
use App\Models\FarmProfile;
use App\Models\FarmSection;
use App\Models\PlantCase;
use App\Models\Prediction;
use App\Models\User;
use App\Services\MockPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RevisedSpecificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bananashield-tests-'.getmypid();
        if (! is_dir($root)) mkdir($root, 0777, true);
        config(['filesystems.disks.local.root' => $root]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function image(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAASwAAAEsCAIAAAD2HxkiAAADWUlEQVR4nO3VMREAIBDEwAc1KEEdopGRZtfAVZlb590BOjvcBkQIPU8IMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECNP6FaEDUuPql+UAAAAASUVORK5CYII=');
        return UploadedFile::fake()->createWithContent('plant.png', $png);
    }

    private function mockPrediction(
        string $predictedClass,
        string $displayLabel,
        float $confidence,
        string $decisionStatus,
        string $path = 'leaf',
        string $viewType = 'whole_leaf',
    ): array {
        return [
            'success' => true,
            'screening_path' => $path,
            'view_type' => $viewType,
            'predicted_class' => $predictedClass,
            'display_label' => $displayLabel,
            'decision_status' => $decisionStatus,
            'confidence' => $confidence,
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

    public function test_role_access_matches_the_three_documented_users(): void
    {
        $owner = $this->user('farm_owner');
        $monitor = $this->user('monitoring_personnel');
        $admin = $this->user('system_administrator');

        $this->actingAs($owner)->get('/analytics')
            ->assertOk()
            ->assertSee('Submission activity')
            ->assertSee('Reports by farm')
            ->assertSee('Screening results')
            ->assertSee('Case status');
        $this->actingAs($owner)->get('/screenings/new')->assertForbidden();
        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertSee('Case review and monitoring')->assertDontSee('Start new screening');
        $this->actingAs($monitor)->get('/screenings/new')
            ->assertOk()
            ->assertSee('Add a clear photograph')
            ->assertSee('Image Capturing Guidelines')
            ->assertDontSee('Choose the main image path')
            ->assertDontSee('Where are the symptoms most visible?')
            ->assertDontSee('name="image_path"', false)
            ->assertDontSee('name="specific_view"', false);
        $this->actingAs($monitor)->get('/dashboard')->assertOk()->assertSee('Guided visual screening')->assertSee('Start new screening');
        $this->actingAs($monitor)->get('/analytics')->assertForbidden();
        $this->actingAs($monitor)->get('/monitoring')->assertOk()->assertSee('Monitor reports by farm block');
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/screenings/new')->assertForbidden();
    }

    public function test_automatic_image_area_analysis_saves_predictions_from_the_shared_four_class_contract(): void
    {
        config(['services.bananashield.mode' => 'mock']);
        $monitor = $this->user('monitoring_personnel');
        $this->mock(MockPredictionService::class, function ($mock) {
            $mock->shouldReceive('predict')->twice()->andReturnUsing(
                fn ($image, $path, $viewType) => $this->mockPrediction(
                    'black_sigatoka',
                    'Black Sigatoka',
                    0.87,
                    'conclusive',
                    $path,
                    $viewType,
                )
            );
        });

        foreach (['NB-L014', 'NB-W027'] as $treeCodename) {
            $this->actingAs($monitor)->post('/screenings', [
                'image' => $this->image(),
                'variety' => 'Cardava',
                'observed_at' => '2026-08-11',
                'farm_section' => 'North Block',
                'tree_codename' => $treeCodename,
            ])->assertOk()->assertSee('Screening complete and case saved');
        }

        $this->assertDatabaseCount('cases', 2);
        $this->assertDatabaseCount('predictions', 2);
        $this->assertDatabaseHas('cases', ['screening_path' => 'auto_detected', 'tree_codename' => 'NB-L014']);
        $this->assertDatabaseHas('cases', ['screening_path' => 'auto_detected', 'tree_codename' => 'NB-W027']);
        $this->assertDatabaseHas('case_images', ['image_path' => 'auto_detected', 'specific_view' => 'auto_detected', 'view_type' => 'auto_detected']);
        foreach (Prediction::pluck('predicted_class') as $class) {
            $this->assertContains($class, ['black_sigatoka', 'fusarium_wilt', 'banana_bunchy_top_disease', 'inconclusive']);
        }

        $owner = $this->user('farm_owner');
        $this->actingAs($owner)->get('/analytics')
            ->assertOk()
            ->assertSee('Reports by farm')
            ->assertViewHas('analytics', fn ($analytics) => $analytics['summary']['total'] === 2);
    }

    public function test_healthy_screenings_are_saved_with_images_and_low_confidence_results_remain_inconclusive(): void
    {
        config(['services.bananashield.mode' => 'mock']);
        $monitor = $this->user('monitoring_personnel');
        $owner = $this->user('farm_owner');
        $farm = FarmProfile::create([
            'farm_name' => 'Healthy Screening Farm',
            'municipality' => 'Bansalan',
            'province' => 'Davao del Sur',
            'managed_by' => $owner->id,
        ]);
        $block = FarmSection::create([
            'farm_profile_id' => $farm->id,
            'name' => 'Block A',
            'active' => true,
            'plant_codenames' => ['BA-H001', 'BA-I002'],
        ]);
        $this->mock(MockPredictionService::class, function ($mock) {
            $mock->shouldReceive('predict')->twice()->andReturn(
                $this->mockPrediction('healthy_banana', 'Healthy Banana', 0.89, 'conclusive'),
                $this->mockPrediction('healthy_banana', 'Healthy Banana', 0.43, 'conclusive'),
            );
        });

        $response = $this->actingAs($monitor)->post('/screenings', [
            'image' => $this->image(),
            'observed_at' => '2026-08-11',
            'farm_profile_id' => $farm->id,
            'farm_section_id' => $block->id,
            'tree_codename' => 'BA-H001',
        ])->assertOk()->assertSee('Screening complete and case saved')
            ->assertDontSee('No farm case was created.')
            ->assertViewHas('case', fn ($case) => $case instanceof PlantCase);
        $case = $response->viewData('case');
        $image = $case->images()->firstOrFail();

        $this->assertDatabaseCount('cases', 1);
        $this->assertDatabaseCount('case_images', 1);
        $this->assertDatabaseCount('predictions', 1);
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'farm_profile_id' => $farm->id, 'farm_section' => 'Block A', 'tree_codename' => 'BA-H001']);
        $this->assertDatabaseHas('predictions', ['case_id' => $case->id, 'predicted_class' => 'healthy_banana']);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($image->storage_path));
        $this->assertDatabaseHas('audit_logs', ['action' => 'screening.case_created', 'entity_id' => $case->id]);
        $this->get("/cases/{$case->id}")->assertOk()->assertSee('Healthy Screening Farm');
        $this->get("/cases/{$case->id}/images/{$image->id}")->assertOk();

        $this->actingAs($monitor)->post('/screenings', [
            'image' => $this->image(),
            'observed_at' => '2026-08-12',
            'farm_profile_id' => $farm->id,
            'farm_section_id' => $block->id,
            'tree_codename' => 'BA-I002',
        ])->assertOk()->assertViewHas('case', fn ($case) => $case instanceof PlantCase);

        $this->assertDatabaseCount('cases', 2);
        $this->assertDatabaseCount('case_images', 2);
        $this->assertDatabaseCount('predictions', 2);
        $this->assertDatabaseHas('predictions', ['predicted_class' => 'inconclusive', 'decision_status' => 'inconclusive']);
        $this->get('/monitoring')->assertOk()->assertViewHas('recordTotal', 2)->assertSee('Healthy Banana');
        $this->get('/dashboard')->assertOk()->assertViewHas('caseCount', 2);
        $this->actingAs($this->user('monitoring_personnel'))->get("/cases/{$case->id}")->assertForbidden();
        $this->get("/cases/{$case->id}/images/{$image->id}")->assertForbidden();
        $this->get('/monitoring?outcome=healthy')->assertOk()->assertViewHas('cases', fn ($cases) => $cases->total() === 0);

        $this->actingAs($owner)->get('/analytics')->assertOk()
            ->assertViewHas('analytics', fn ($analytics) =>
                $analytics['summary'] === ['total' => 2, 'healthy' => 1, 'disease' => 0, 'inconclusive' => 1, 'unavailable' => 0]
            );
        $this->get('/monitoring?outcome=healthy')->assertOk()->assertViewHas('cases', fn ($cases) => $cases->total() === 1);
        $this->get('/monitoring?decision=inconclusive')->assertOk()->assertViewHas('cases', fn ($cases) => $cases->total() === 1);
        $this->get('/monitoring?decision=conclusive')->assertOk()->assertViewHas('cases', fn ($cases) => $cases->total() === 1);
        $this->post("/cases/{$case->id}/review", ['review_status' => 'reviewed'])->assertRedirect();
        $this->assertDatabaseHas('predictions', ['case_id' => $case->id, 'predicted_class' => 'healthy_banana']);
    }

    public function test_screening_context_uses_registered_farms_blocks_and_plant_codenames(): void
    {
        config(['services.bananashield.mode' => 'mock']);
        $owner = $this->user('farm_owner');
        $monitor = $this->user('monitoring_personnel');
        $farm = FarmProfile::create([
            'farm_name' => 'San Isidro Banana Farm',
            'municipality' => 'Bansalan',
            'province' => 'Davao del Sur',
            'managed_by' => $owner->id,
        ]);
        $northBlock = FarmSection::create([
            'farm_profile_id' => $farm->id,
            'name' => 'North Block',
            'active' => true,
            'plant_codenames' => ['NB-P001', 'NB-P002'],
        ]);
        $otherFarm = FarmProfile::create([
            'farm_name' => 'Riverside Banana Farm',
            'municipality' => 'Padada',
            'province' => 'Davao del Sur',
            'managed_by' => $owner->id,
        ]);
        $otherBlock = FarmSection::create([
            'farm_profile_id' => $otherFarm->id,
            'name' => 'Riverside Block',
            'active' => true,
            'plant_codenames' => ['RB-P001'],
        ]);

        $this->actingAs($monitor)->get('/screenings/new')
            ->assertOk()
            ->assertSee('name="farm_profile_id"', false)
            ->assertSee('name="farm_section_id"', false)
            ->assertSee('San Isidro Banana Farm')
            ->assertSee('Registered codenames only');

        $this->mock(MockPredictionService::class, function ($mock) {
            $mock->shouldReceive('predict')->once()->andReturn(
                $this->mockPrediction('black_sigatoka', 'Black Sigatoka', 0.87, 'conclusive')
            );
        });

        $this->actingAs($monitor)->post('/screenings', [
            'image' => $this->image(),
            'observed_at' => '2026-08-11',
            'farm_profile_id' => $farm->id,
            'farm_section_id' => $northBlock->id,
            'tree_codename' => 'NB-P001',
        ])->assertOk()->assertSee('Screening complete and case saved');

        $this->assertDatabaseHas('cases', [
            'farm_profile_id' => $farm->id,
            'farm_section' => 'North Block',
            'tree_codename' => 'NB-P001',
        ]);

        $this->actingAs($monitor)->from('/screenings/new')->post('/screenings', [
            'image' => $this->image(),
            'observed_at' => '2026-08-11',
            'farm_profile_id' => $farm->id,
            'farm_section_id' => $otherBlock->id,
            'tree_codename' => 'RB-P001',
        ])->assertRedirect('/screenings/new')->assertSessionHasErrors('farm_section_id');

        $this->actingAs($monitor)->from('/screenings/new')->post('/screenings', [
            'image' => $this->image(),
            'observed_at' => '2026-08-11',
            'farm_profile_id' => $farm->id,
            'farm_section_id' => $northBlock->id,
            'tree_codename' => 'RB-P001',
        ])->assertRedirect('/screenings/new')->assertSessionHasErrors('tree_codename');
    }
    public function test_follow_ups_can_be_saved_with_or_without_an_image_without_selecting_a_view(): void
    {
        $monitor = $this->user('monitoring_personnel');
        $case = PlantCase::create([
            'case_number' => 'BS-FOLLOWUP-NOVIEW',
            'submitted_by' => $monitor->id,
            'screening_path' => 'auto_detected',
            'observed_at' => '2026-09-05',
            'status' => 'open',
            'review_status' => 'pending',
        ]);
        $url = "/cases/{$case->id}/follow-ups";
        $this->actingAs($monitor)->get("/cases/{$case->id}")
            ->assertOk()->assertDontSee('Follow-up view')->assertDontSee('name="view_type"', false);

        $this->from("/cases/{$case->id}")->post($url, [
            'observation' => 'No visible change.', 'case_status' => 'unchanged',
        ])->assertRedirect("/cases/{$case->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseCount('follow_ups', 1);
        $this->assertDatabaseCount('case_images', 0);

        $this->post($url, [
            'observation' => 'Updated photograph.', 'case_status' => 'improving',
            'image' => $this->image(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('follow_ups', 2);
        $this->assertDatabaseCount('case_images', 1);
        $image = $case->images()->firstOrFail();
        $this->assertSame('unspecified', $image->view_type);
        $this->assertSame('follow_up', $image->image_type);
        $this->assertNotNull($image->follow_up_id);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($image->storage_path));
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'status' => 'improving']);
        $this->get("/cases/{$case->id}/images/{$image->id}")->assertOk();

        $this->post($url, [
            'observation' => 'Invalid file.', 'case_status' => 'unchanged',
            'image' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors('image');
        $this->actingAs($this->user('monitoring_personnel'))->post($url, [
            'observation' => 'Not this reporter.', 'case_status' => 'unchanged',
        ])->assertForbidden();
        $this->assertDatabaseCount('follow_ups', 2);
    }


    public function test_image_area_is_automatically_assigned_without_manual_input(): void
    {
        config(['services.bananashield.mode' => 'mock']);
        $monitor = $this->user('monitoring_personnel');
        $this->mock(MockPredictionService::class, function ($mock) {
            $mock->shouldReceive('predict')->once()->andReturn(
                $this->mockPrediction('black_sigatoka', 'Black Sigatoka', 0.87, 'conclusive')
            );
        });

        $this->actingAs($monitor)->post('/screenings', [
            'image' => $this->image(),
            'observed_at' => '2026-08-11',
        ])->assertOk()->assertSee('Screening complete and case saved');

        $this->assertDatabaseCount('cases', 1);
        $this->assertDatabaseHas('cases', ['screening_path' => 'auto_detected']);
        $this->assertDatabaseHas('case_images', [
            'image_path' => 'auto_detected',
            'specific_view' => 'auto_detected',
            'view_type' => 'auto_detected',
        ]);
    }

    public function test_case_and_report_counts_use_the_same_role_scoped_records(): void
    {
        $owner = $this->user('farm_owner');
        $monitor = $this->user('monitoring_personnel');
        $otherMonitor = $this->user('monitoring_personnel');

        foreach ([
            ['BS-2026-COUNT001', $monitor->id],
            ['BS-2026-COUNT002', $monitor->id],
            ['BS-2026-COUNT003', $otherMonitor->id],
        ] as [$caseNumber, $submittedBy]) {
            PlantCase::create([
                'case_number' => $caseNumber,
                'submitted_by' => $submittedBy,
                'screening_path' => 'leaf',
                'observed_at' => '2026-08-11',
                'status' => 'open',
                'review_status' => 'pending',
            ]);
        }

        $this->actingAs($monitor)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('caseCount', 2)
            ->assertSee('Reports you submitted');
        $this->actingAs($monitor)->get('/monitoring')
            ->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 2)
            ->assertSee('2')
            ->assertSee('submitted reports');

        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('caseCount', 3)
            ->assertViewHas('pendingReviewCount', 3)
            ->assertSee(route('monitoring'), false)
            ->assertSee(route('monitoring', ['review' => 'pending']), false);
        $this->actingAs($owner)->get('/monitoring')
            ->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 3)
            ->assertSee('3')
            ->assertSee('farm cases');
        $this->actingAs($owner)->get('/monitoring?review=pending')
            ->assertOk()
            ->assertViewHas('selectedReview', 'pending')
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 3)
            ->assertSee('Cases awaiting owner review');
    }

    public function test_monitoring_reports_can_be_browsed_and_searched_by_block(): void
    {
        $owner = $this->user('farm_owner');
        $firstMonitor = User::factory()->create([
            'name' => 'Ana Field Monitor',
            'email' => 'ana.monitor@example.test',
            'role' => 'monitoring_personnel',
            'status' => 'active',
        ]);
        $secondMonitor = User::factory()->create([
            'name' => 'Ben Field Monitor',
            'email' => 'ben.monitor@example.test',
            'role' => 'monitoring_personnel',
            'status' => 'active',
        ]);

        foreach ([
            ['BS-2026-BLOCKA01', $firstMonitor->id, 'Block A', 'BA-T001', 'open', 'Lakatan'],
            ['BS-2026-BLOCKA02', $secondMonitor->id, 'Block A', 'BA-T002', 'improving', 'Cardava'],
            ['BS-2026-BLOCKB01', $secondMonitor->id, 'Block B', 'BB-T001', 'worsening', 'Saba'],
        ] as [$caseNumber, $submittedBy, $block, $treeCodename, $status, $variety]) {
            PlantCase::create([
                'case_number' => $caseNumber,
                'submitted_by' => $submittedBy,
                'screening_path' => 'leaf',
                'variety' => $variety,
                'farm_section' => $block,
                'tree_codename' => $treeCodename,
                'observed_at' => '2026-08-11',
                'status' => $status,
                'review_status' => 'pending',
            ]);
        }

        $this->actingAs($owner)->get('/monitoring')
            ->assertOk()
            ->assertSee('All farm blocks')
            ->assertSee('name="block"', false)
            ->assertDontSee('class="block-tile"', false)
            ->assertSee('Block A')
            ->assertSee('Block B')
            ->assertSee('Case number, plant codename, reporter...', false)
            ->assertViewHas('recordTotal', 3)
            ->assertViewHas('blocks', fn ($blocks) => $blocks->firstWhere('name', 'Block A')['reports_count'] === 2);

        $this->actingAs($owner)->get('/monitoring?block=Block%20A')
            ->assertOk()
            ->assertSee('Block A reports')
            ->assertSee('BS-2026-BLOCKA01')
            ->assertSee('BS-2026-BLOCKA02')
            ->assertDontSee('BS-2026-BLOCKB01')
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 2);

        $this->actingAs($owner)->get('/monitoring?q=Ana%20Field%20Monitor')
            ->assertOk()
            ->assertSee('BS-2026-BLOCKA01')
            ->assertDontSee('BS-2026-BLOCKA02')
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 1);

        $this->actingAs($owner)->get('/monitoring?q=Block%20B&status=worsening')
            ->assertOk()
            ->assertSee('BS-2026-BLOCKB01')
            ->assertDontSee('BS-2026-BLOCKA01')
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 1);

        $this->actingAs($owner)->get('/monitoring?q=BA-T002')
            ->assertOk()
            ->assertSee('BS-2026-BLOCKA02')
            ->assertDontSee('BS-2026-BLOCKA01')
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 1);

        $this->actingAs($firstMonitor)->get('/monitoring?q=Ben%20Field%20Monitor')
            ->assertOk()
            ->assertViewHas('recordTotal', 1)
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 0)
            ->assertDontSee('BS-2026-BLOCKA02')
            ->assertDontSee('BS-2026-BLOCKB01');
    }

    public function test_ai_service_failure_creates_no_completed_prediction(): void
    {
        config(['services.bananashield.mode' => 'model']);
        Http::fake(['*' => Http::response(['detail' => 'unavailable'], 503)]);
        $monitor = $this->user('monitoring_personnel');

        $this->actingAs($monitor)->from('/screenings/new')->post('/screenings', [
            'image_path' => 'leaf',
            'specific_view' => 'leaf_surface',
            'image' => $this->image(),
            'observed_at' => '2026-08-11',
        ])->assertRedirect('/screenings/new')->assertSessionHasErrors('image');

        $this->assertDatabaseCount('cases', 0);
        $this->assertDatabaseCount('predictions', 0);
    }

    public function test_owner_can_review_monitoring_personnel_report_without_changing_prediction(): void
    {
        $owner = $this->user('farm_owner');
        $monitor = $this->user('monitoring_personnel');
        $case = PlantCase::create([
            'case_number' => 'BS-2026-TEST0001', 'submitted_by' => $monitor->id, 'screening_path' => 'leaf',
            'observed_at' => '2026-08-11', 'status' => 'open', 'review_status' => 'pending',
        ]);

        $this->actingAs($monitor)->get("/cases/{$case->id}")->assertOk()->assertSee('Farm case history');

        $this->actingAs($owner)->post("/cases/{$case->id}/follow-ups", [
            'observation' => 'Owner should not create monitoring follow-ups.',
            'case_status' => 'open',
        ])->assertForbidden();

        $this->actingAs($owner)->post("/cases/{$case->id}/review", [
            'review_status' => 'needs_follow_up', 'review_notes' => 'Capture another leaf image in daylight.',
        ])->assertRedirect();

        $this->assertDatabaseHas('cases', ['id' => $case->id, 'review_status' => 'needs_follow_up', 'reviewed_by' => $owner->id]);
    }

    public function test_farm_owner_can_add_and_edit_managed_farms_and_blocks(): void
    {
        $owner = $this->user('farm_owner');

        $this->actingAs($owner)->get('/farm-settings')
            ->assertOk()
            ->assertSee('Manage farms')
            ->assertSee('Add a farm');

        $this->actingAs($owner)->post('/farm-settings/farms', [
            'farm_name' => 'San Isidro Banana Farm',
            'barangay' => 'San Isidro',
            'municipality' => 'Bansalan',
            'province' => 'Davao del Sur',
            'total_area_hectares' => '12.50',
            'primary_varieties' => 'Cardava, Tundan',
        ])->assertRedirect()->assertSessionHas('success');

        $farm = FarmProfile::query()
            ->where('farm_name', 'San Isidro Banana Farm')
            ->where('managed_by', $owner->id)
            ->firstOrFail();

        $this->actingAs($owner)->post("/farm-settings/farms/{$farm->id}/blocks", [
            'name' => 'North Block',
            'area_hectares' => '4.25',
            'notes' => 'Upper field section',
            'plant_codenames' => "NB-P001\nNB-P002",
        ])->assertRedirect()->assertSessionHas('success');

        $block = FarmSection::query()
            ->where('farm_profile_id', $farm->id)
            ->where('name', 'North Block')
            ->firstOrFail();
        $this->assertSame(['NB-P001', 'NB-P002'], $block->plant_codenames);

        $this->actingAs($owner)->post("/farm-settings/farms/{$farm->id}/blocks/batch", [
            'blocks' => [
                [
                    'name' => 'East Block',
                    'area_hectares' => '3.00',
                    'notes' => 'Near the irrigation canal',
                    'plant_codenames' => "EB-P001\nEB-P002",
                ],
                [
                    'name' => 'South Block',
                    'area_hectares' => '2.75',
                    'notes' => 'Lower field section',
                    'plant_codenames' => 'SB-P001, SB-P002',
                ],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('farm_sections', ['farm_profile_id' => $farm->id, 'name' => 'East Block']);
        $this->assertSame(['SB-P001', 'SB-P002'], FarmSection::where('farm_profile_id', $farm->id)->where('name', 'South Block')->firstOrFail()->plant_codenames);

        $this->actingAs($owner)->patch("/farm-settings/farms/{$farm->id}/blocks/{$block->id}", [
            'name' => 'North Block',
            'area_hectares' => '4.50',
            'notes' => 'Upper field section near the access road',
            'plant_codenames' => 'NB-P001, NB-P002, NB-P003',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(['NB-P001', 'NB-P002', 'NB-P003'], $block->refresh()->plant_codenames);
        $this->assertSame('4.50', $block->area_hectares);

        $this->actingAs($owner)->patch("/farm-settings/farms/{$farm->id}", [
            'farm_name' => 'San Isidro Banana Farm — North',
            'barangay' => 'San Isidro',
            'municipality' => 'Bansalan',
            'province' => 'Davao del Sur',
            'total_area_hectares' => '14.75',
            'primary_varieties' => 'Cardava, Tundan, Lakatan',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('farm_profiles', [
            'id' => $farm->id,
            'farm_name' => 'San Isidro Banana Farm — North',
            'managed_by' => $owner->id,
        ]);

        $monitor = $this->user('monitoring_personnel');
        $this->actingAs($monitor)->get('/farm-settings')->assertForbidden();
    }
    public function test_farm_owner_can_create_monitoring_personnel_accounts_only(): void
    {
        $owner = $this->user('farm_owner');

        $this->actingAs($owner)->get('/farm-settings')
            ->assertOk()
            ->assertDontSee('Monitoring personnel accounts');

        $this->actingAs($owner)->get('/accounts')
            ->assertOk()
            ->assertSee('Monitoring personnel accounts')
            ->assertSee('Create monitoring account')
            ->assertSee('Password')
            ->assertDontSee('Temporary password')
            ->assertSeeInOrder(['Create monitoring account', 'No monitoring accounts yet']);

        $this->actingAs($owner)->post('/accounts', [
            'monitor_name' => 'Field Monitor',
            'monitor_email' => 'field.monitor@example.test',
            'monitor_password' => 'SecurePass123',
            'monitor_password_confirmation' => 'SecurePass123',
            'role' => 'system_administrator',
        ])->assertRedirect()->assertSessionHas('success');

        $account = User::where('email', 'field.monitor@example.test')->firstOrFail();
        $this->assertSame('monitoring_personnel', $account->role);
        $this->assertSame('active', $account->status);
        $this->assertTrue(Hash::check('SecurePass123', $account->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'farm.monitoring_account_created',
            'entity_id' => $account->id,
        ]);

        $this->actingAs($owner)->get('/accounts')
            ->assertOk()
            ->assertSee('class="account-scroll"', false)
            ->assertSee('class="account-card"', false)
            ->assertSee('Edit')
            ->assertDontSee('Delete account');

        PlantCase::create([
            'case_number' => 'BS-2026-ACCOUNT01',
            'submitted_by' => $account->id,
            'screening_path' => 'leaf',
            'observed_at' => '2026-08-12',
            'status' => 'open',
            'review_status' => 'pending',
        ]);

        $this->actingAs($owner)->get("/accounts/{$account->id}")
            ->assertOk()
            ->assertSee('Monitoring account details')
            ->assertSee('1')
            ->assertSee('Submitted reports')
            ->assertSee('Delete account')
            ->assertSee('This account has farm records and cannot be deleted.');

        $this->actingAs($owner)->patch("/accounts/{$account->id}", [
            'name' => 'Updated Field Monitor',
            'email' => 'updated.monitor@example.test',
            'status' => 'inactive',
            'password' => 'UpdatedPass123',
            'password_confirmation' => 'UpdatedPass123',
            'role' => 'system_administrator',
        ])->assertRedirect()->assertSessionHas('success');

        $account->refresh();
        $this->assertSame('Updated Field Monitor', $account->name);
        $this->assertSame('updated.monitor@example.test', $account->email);
        $this->assertSame('inactive', $account->status);
        $this->assertSame('monitoring_personnel', $account->role);
        $this->assertTrue(Hash::check('UpdatedPass123', $account->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'farm.monitoring_account_updated',
            'entity_id' => $account->id,
        ]);

        $this->actingAs($owner)->delete("/accounts/{$account->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('account');
        $this->assertDatabaseHas('users', ['id' => $account->id]);

        $deletableAccount = User::factory()->create([
            'name' => 'Unused Monitor',
            'email' => 'unused.monitor@example.test',
            'role' => 'monitoring_personnel',
            'status' => 'active',
        ]);
        $this->actingAs($owner)->get("/accounts/{$deletableAccount->id}")
            ->assertOk()
            ->assertSee('Delete account')
            ->assertSee('Permanently remove this unused account.');
        $this->actingAs($owner)->delete("/accounts/{$deletableAccount->id}")
            ->assertRedirect('/accounts')
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $deletableAccount->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'farm.monitoring_account_deleted',
            'entity_id' => $deletableAccount->id,
        ]);

        $this->actingAs($owner)->get("/accounts/{$owner->id}")->assertNotFound();

        $monitor = $this->user('monitoring_personnel');
        $this->actingAs($monitor)->get('/accounts')->assertForbidden();
        $this->actingAs($monitor)->get("/accounts/{$account->id}")->assertForbidden();
        $this->actingAs($monitor)->post('/accounts', [
            'monitor_name' => 'Unauthorized User',
            'monitor_email' => 'unauthorized@example.test',
            'monitor_password' => 'SecurePass123',
            'monitor_password_confirmation' => 'SecurePass123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.test']);
    }
}
