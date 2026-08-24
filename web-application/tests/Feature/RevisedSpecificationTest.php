<?php

namespace Tests\Feature;

use App\Models\CaseImage;
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
            ->assertSee('Capture path mix')
            ->assertSee('Class distribution')
            ->assertSee('Case status');
        $this->actingAs($owner)->get('/screenings/new')->assertForbidden();
        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertSee('Case review and monitoring')->assertDontSee('Start new screening');
        $this->actingAs($monitor)->get('/screenings/new')
            ->assertOk()
            ->assertSee('Choose the main image path')
            ->assertSee('Where are the symptoms most visible?')
            ->assertSee('Leaf Underside')
            ->assertSee('Pseudostem &amp; Base', false)
            ->assertSee('Capture Guide')
            ->assertSee('same four-class EfficientNet-B0 model')
            ->assertSee('Banana tree codename')
            ->assertDontSee('Required image view')
            ->assertDontSee('name="view_type"', false);
        $this->actingAs($monitor)->get('/dashboard')->assertOk()->assertSee('Guided visual screening')->assertSee('Start new screening');
        $this->actingAs($monitor)->get('/analytics')->assertForbidden();
        $this->actingAs($monitor)->get('/monitoring')->assertOk()->assertSee('Monitor reports by farm block');
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/screenings/new')->assertForbidden();
    }

    public function test_both_capture_paths_save_predictions_from_the_shared_four_class_contract(): void
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

        foreach ([['leaf', 'leaf_underside', 'NB-L014'], ['whole_plant', 'crown_upper_leaves', 'NB-W027']] as [$path, $specificView, $treeCodename]) {
            $this->actingAs($monitor)->post('/screenings', [
                'image_path' => $path,
                'specific_view' => $specificView,
                'image' => $this->image(),
                'variety' => 'Cardava',
                'observed_at' => '2026-08-11',
                'farm_section' => 'North Block',
                'tree_codename' => $treeCodename,
            ])->assertOk()->assertSee('Screening complete and case saved');
        }

        $this->assertDatabaseCount('cases', 2);
        $this->assertDatabaseCount('predictions', 2);
        $this->assertDatabaseHas('cases', ['farm_section' => 'North Block', 'tree_codename' => 'NB-L014']);
        $this->assertDatabaseHas('cases', ['farm_section' => 'North Block', 'tree_codename' => 'NB-W027']);
        $this->assertDatabaseHas('case_images', ['image_path' => 'leaf', 'specific_view' => 'leaf_underside', 'view_type' => 'leaf_underside']);
        $this->assertDatabaseHas('case_images', ['image_path' => 'whole_plant', 'specific_view' => 'crown_upper_leaves', 'view_type' => 'crown_upper_leaves']);
        foreach (Prediction::pluck('predicted_class') as $class) {
            $this->assertContains($class, ['black_sigatoka', 'fusarium_wilt', 'banana_bunchy_top_disease', 'inconclusive']);
        }

        $owner = $this->user('farm_owner');
        $this->actingAs($owner)->get('/analytics')
            ->assertOk()
            ->assertSee('50 percent leaf screening and 50 percent whole-plant screening')
            ->assertSee('<b>2</b> cases', false);
    }

    public function test_healthy_screenings_are_not_saved_but_inconclusive_screenings_are(): void
    {
        config(['services.bananashield.mode' => 'mock']);
        $monitor = $this->user('monitoring_personnel');
        $this->mock(MockPredictionService::class, function ($mock) {
            $mock->shouldReceive('predict')->twice()->andReturn(
                $this->mockPrediction('healthy_banana', 'Healthy Banana', 0.89, 'conclusive'),
                $this->mockPrediction('inconclusive', 'Inconclusive result', 0.43, 'inconclusive'),
            );
        });

        $this->actingAs($monitor)->post('/screenings', [
            'image_path' => 'leaf',
            'specific_view' => 'whole_leaf',
            'image' => $this->image(),
            'observed_at' => '2026-08-11',
            'farm_section' => 'Block A',
            'tree_codename' => 'BA-H001',
        ])->assertOk()
            ->assertSee('Healthy screening—not added to reports.')
            ->assertSee('No farm case was created.')
            ->assertViewHas('case', null)
            ->assertViewHas('imagePreview', fn ($preview) => str_starts_with($preview, 'data:image/png;base64,'));

        $this->assertDatabaseCount('cases', 0);
        $this->assertDatabaseCount('case_images', 0);
        $this->assertDatabaseCount('predictions', 0);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $monitor->id,
            'action' => 'screening.healthy_result_completed',
        ]);

        $this->actingAs($monitor)->post('/screenings', [
            'image_path' => 'leaf',
            'specific_view' => 'whole_leaf',
            'image' => $this->image(),
            'observed_at' => '2026-08-12',
            'farm_section' => 'Block A',
            'tree_codename' => 'BA-I002',
        ])->assertOk()
            ->assertSee('Screening complete and case saved')
            ->assertViewHas('case', fn ($case) => $case instanceof PlantCase);

        $this->assertDatabaseCount('cases', 1);
        $this->assertDatabaseCount('case_images', 1);
        $this->assertDatabaseHas('predictions', ['predicted_class' => 'inconclusive']);
        $this->assertDatabaseHas('cases', ['tree_codename' => 'BA-I002']);

        $legacyHealthyCase = PlantCase::create([
            'case_number' => 'BS-2026-LEGACYHEALTHY',
            'submitted_by' => $monitor->id,
            'screening_path' => 'leaf',
            'observed_at' => '2026-08-10',
            'farm_section' => 'Block A',
            'status' => 'open',
            'review_status' => 'pending',
        ]);
        $legacyImage = CaseImage::create([
            'case_id' => $legacyHealthyCase->id,
            'view_type' => 'close_up_leaf',
            'image_type' => 'original',
            'storage_disk' => 'local',
            'storage_path' => 'legacy/healthy.png',
            'original_filename' => 'healthy.png',
            'mime_type' => 'image/png',
            'file_size' => 100,
            'width' => 300,
            'height' => 300,
            'image_quality_status' => 'accepted',
            'metadata_removed' => true,
            'uploaded_at' => now(),
        ]);
        Prediction::create([
            'case_id' => $legacyHealthyCase->id,
            'image_id' => $legacyImage->id,
            'predicted_class' => 'healthy_banana',
            'display_label' => 'Healthy Banana',
            'confidence' => 0.90,
            'decision_status' => 'conclusive',
            'quality_status' => 'accepted',
            'quality_flags' => [],
            'result_message' => 'Legacy healthy result.',
            'disclaimer' => 'Preliminary result only.',
        ]);

        $this->actingAs($monitor)->get('/monitoring')
            ->assertOk()
            ->assertViewHas('recordTotal', 1)
            ->assertDontSee('BS-2026-LEGACYHEALTHY');
        $this->actingAs($monitor)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('caseCount', 1);
        $this->actingAs($monitor)->get("/cases/{$legacyHealthyCase->id}")->assertNotFound();

        $owner = $this->user('farm_owner');
        $this->actingAs($owner)->get('/analytics')
            ->assertOk()
            ->assertDontSee('Healthy Banana')
            ->assertSee(route('monitoring'), false)
            ->assertSee(route('monitoring', ['decision' => 'conclusive']), false)
            ->assertSee(route('monitoring', ['decision' => 'inconclusive']), false)
            ->assertSee(route('monitoring', ['status' => 'referred']), false);
        $this->actingAs($owner)->get('/monitoring?decision=inconclusive')
            ->assertOk()
            ->assertViewHas('selectedDecision', 'inconclusive')
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 1)
            ->assertSee('Inconclusive results');
        $this->actingAs($owner)->get('/monitoring?decision=conclusive')
            ->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 0)
            ->assertSee('Conclusive results');
    }

    public function test_specific_view_must_match_the_image_path_and_is_stored_as_metadata(): void
    {
        config(['services.bananashield.mode' => 'mock']);
        $monitor = $this->user('monitoring_personnel');
        $this->mock(MockPredictionService::class, function ($mock) {
            $mock->shouldReceive('predict')->once()->andReturn(
                $this->mockPrediction('black_sigatoka', 'Black Sigatoka', 0.87, 'conclusive')
            );
        });

        $this->actingAs($monitor)->from('/screenings/new')->post('/screenings', [
            'image_path' => 'leaf',
            'specific_view' => 'full_plant',
            'image' => $this->image(),
            'observed_at' => '2026-08-11',
        ])->assertRedirect('/screenings/new')->assertSessionHasErrors('specific_view');

        $this->assertDatabaseCount('cases', 0);

        $this->actingAs($monitor)->post('/screenings', [
            'image_path' => 'leaf',
            'specific_view' => 'leaf_underside',
            'image' => $this->image(),
            'observed_at' => '2026-08-11',
        ])->assertOk()->assertSee('Screening complete and case saved');

        $this->assertDatabaseCount('cases', 1);
        $this->assertDatabaseHas('cases', ['screening_path' => 'leaf']);
        $this->assertDatabaseHas('case_images', [
            'image_path' => 'leaf',
            'specific_view' => 'leaf_underside',
            'view_type' => 'leaf_underside',
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
            ->assertSee('Search reporter, tree codename, block, case number', false)
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

    public function test_farm_owner_can_manage_profile_sections_and_notification_preferences(): void
    {
        $owner = $this->user('farm_owner');

        $this->actingAs($owner)->get('/farm-settings')
            ->assertOk()
            ->assertSee('Farm profile and settings')
            ->assertSee('Sections and blocks');

        $this->actingAs($owner)->patch('/farm-settings', [
            'farm_name' => 'San Isidro Banana Farm',
            'barangay' => 'San Isidro',
            'municipality' => 'Bansalan',
            'province' => 'Davao del Sur',
            'total_area_hectares' => '12.50',
            'primary_varieties' => 'Cardava, Tundan',
            'notification_email' => 'owner@example.test',
            'case_updates' => '1',
            'referral_alerts' => '1',
            'weekly_summary' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('farm_profiles', [
            'farm_name' => 'San Isidro Banana Farm',
            'municipality' => 'Bansalan',
            'managed_by' => $owner->id,
        ]);

        $this->actingAs($owner)->post('/farm-settings/sections', [
            'name' => 'North Block',
            'area_hectares' => '4.25',
            'notes' => 'Upper field section',
        ])->assertRedirect();

        $this->assertDatabaseHas('farm_sections', [
            'name' => 'North Block',
            'area_hectares' => '4.25',
            'active' => true,
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
