<?php

namespace App\Http\Controllers;

use App\Models\FarmProfile;
use App\Models\FarmSection;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FarmSettingsController extends Controller
{
    private const DEFAULT_NOTIFICATIONS = [
        'case_updates' => true,
        'referral_alerts' => true,
        'weekly_summary' => false,
    ];

    public function index(Request $request)
    {
        $farms = FarmProfile::query()
            ->where('managed_by', $request->user()->id)
            ->with('sections')
            ->latest('updated_at')
            ->get();

        return view('farm-settings.index', compact('farms'));
    }

    public function storeFarm(Request $request, AuditLogger $audit)
    {
        $farm = FarmProfile::create(array_merge($this->farmData($request), [
            'managed_by' => $request->user()->id,
            'notification_preferences' => self::DEFAULT_NOTIFICATIONS,
        ]));
        $audit->record('farm.created', $farm);

        return back()->with('success', 'Farm added.');
    }

    public function updateFarm(Request $request, FarmProfile $farm, AuditLogger $audit)
    {
        abort_unless($farm->managed_by === $request->user()->id, 404);

        $farm->update($this->farmData($request));
        $audit->record('farm.updated', $farm);

        return back()->with('success', 'Farm details updated.');
    }

    public function storeFarmSection(Request $request, FarmProfile $farm, AuditLogger $audit)
    {
        $this->ensureManagedFarm($request, $farm);
        $data = $this->sectionData($request, $farm);

        $section = $farm->sections()->create(array_merge($data, [
            'plant_codenames' => $this->plantCodenames($data['plant_codenames'] ?? null),
            'active' => true,
        ]));
        $audit->record('farm.block_created', $section);

        return back()->with('success', 'Farm block added.');
    }

    public function storeBlocks(Request $request, AuditLogger $audit)
    {
        $farmId = $request->validate([
            'farm_id' => ['required', 'integer', Rule::exists('farm_profiles', 'id')],
        ])['farm_id'];

        $farm = FarmProfile::query()
            ->whereKey($farmId)
            ->where('managed_by', $request->user()->id)
            ->firstOrFail();

        return $this->storeFarmSections($request, $farm, $audit);
    }

    public function storeFarmSections(Request $request, FarmProfile $farm, AuditLogger $audit)
    {
        $this->ensureManagedFarm($request, $farm);
        $data = $request->validate([
            'blocks' => 'required|array|min:1|max:20',
            'blocks.*.name' => 'required|string|max:120',
            'blocks.*.area_hectares' => 'nullable|numeric|min:0|max:999999.99',
            'blocks.*.notes' => 'nullable|string|max:1000',
            'blocks.*.plant_codenames' => 'nullable|string|max:2000',
        ]);

        $names = collect($data['blocks'])->pluck('name')->map(fn ($name) => trim($name));
        if ($names->count() !== $names->unique()->count()) {
            throw ValidationException::withMessages(['blocks' => 'Each new block needs a unique name.']);
        }

        $existingName = FarmSection::query()
            ->where('farm_profile_id', $farm->id)
            ->whereIn('name', $names)
            ->value('name');
        if ($existingName) {
            throw ValidationException::withMessages(['blocks' => "$existingName is already registered for this farm."]);
        }

        foreach ($data['blocks'] as $block) {
            $section = $farm->sections()->create([
                'name' => trim($block['name']),
                'area_hectares' => $block['area_hectares'] ?? null,
                'notes' => $block['notes'] ?? null,
                'plant_codenames' => $this->plantCodenames($block['plant_codenames'] ?? null),
                'active' => true,
            ]);
            $audit->record('farm.block_created', $section);
        }

        return back()->with('success', count($data['blocks']).' farm blocks added.');
    }

    public function updateFarmSection(Request $request, FarmProfile $farm, FarmSection $section, AuditLogger $audit)
    {
        $this->ensureManagedFarm($request, $farm);
        abort_unless($section->farm_profile_id === $farm->id, 404);
        $data = $this->sectionData($request, $farm, $section);

        $section->update([
            'name' => $data['name'],
            'area_hectares' => $data['area_hectares'] ?? null,
            'notes' => $data['notes'] ?? null,
            'plant_codenames' => $this->plantCodenames($data['plant_codenames'] ?? null),
        ]);
        $audit->record('farm.block_updated', $section);

        return back()->with('success', 'Farm block details updated.');
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'farm_name' => 'required|string|max:120',
            'barangay' => 'nullable|string|max:120',
            'municipality' => 'required|string|max:120',
            'province' => 'required|string|max:120',
            'total_area_hectares' => 'nullable|numeric|min:0|max:999999.99',
            'primary_varieties' => 'nullable|string|max:1000',
            'notification_email' => 'nullable|email|max:255',
            'case_updates' => 'required|boolean',
            'referral_alerts' => 'required|boolean',
            'weekly_summary' => 'required|boolean',
        ]);

        $profile = $this->profile($request);
        $profile->update([
            'farm_name' => $data['farm_name'],
            'barangay' => $data['barangay'] ?? null,
            'municipality' => $data['municipality'],
            'province' => $data['province'],
            'total_area_hectares' => $data['total_area_hectares'] ?? null,
            'primary_varieties' => $data['primary_varieties'] ?? null,
            'notification_email' => $data['notification_email'] ?? null,
            'notification_preferences' => [
                'case_updates' => (bool) $data['case_updates'],
                'referral_alerts' => (bool) $data['referral_alerts'],
                'weekly_summary' => (bool) $data['weekly_summary'],
            ],
            'managed_by' => $request->user()->id,
        ]);

        $audit->record('farm.profile_updated', $profile);

        return back()->with('success', 'Farm information and notification preferences updated.');
    }

    public function storeSection(Request $request, AuditLogger $audit)
    {
        $profile = $this->profile($request);
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('farm_sections', 'name')->where(fn ($query) => $query->where('farm_profile_id', $profile->id)),
            ],
            'area_hectares' => 'nullable|numeric|min:0|max:999999.99',
            'notes' => 'nullable|string|max:1000',
            'plant_codenames' => 'nullable|string|max:2000',
        ]);

        $section = $profile->sections()->create(array_merge($data, [
            'plant_codenames' => $this->plantCodenames($data['plant_codenames'] ?? null),
            'active' => true,
        ]));
        $audit->record('farm.section_created', $section);

        return back()->with('success', 'Farm section added.');
    }

    public function updateSection(Request $request, FarmSection $section, AuditLogger $audit)
    {
        $profile = $this->profile($request);
        abort_unless($section->farm_profile_id === $profile->id, 404);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('farm_sections', 'name')
                    ->where(fn ($query) => $query->where('farm_profile_id', $profile->id))
                    ->ignore($section->id),
            ],
            'area_hectares' => 'nullable|numeric|min:0|max:999999.99',
            'notes' => 'nullable|string|max:1000',
            'plant_codenames' => 'nullable|string|max:2000',
            'active' => 'required|boolean',
        ]);

        $section->update([
            'name' => $data['name'],
            'area_hectares' => $data['area_hectares'] ?? null,
            'notes' => $data['notes'] ?? null,
            'plant_codenames' => $this->plantCodenames($data['plant_codenames'] ?? null),
            'active' => (bool) $data['active'],
        ]);
        $audit->record('farm.section_updated', $section, metadata: ['active' => $section->active]);

        return back()->with('success', 'Farm section updated.');
    }

    private function ensureManagedFarm(Request $request, FarmProfile $farm): void
    {
        abort_unless($farm->managed_by === $request->user()->id, 404);
    }

    private function sectionData(Request $request, FarmProfile $farm, ?FarmSection $section = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('farm_sections', 'name')
                    ->where(fn ($query) => $query->where('farm_profile_id', $farm->id))
                    ->ignore($section?->id),
            ],
            'area_hectares' => 'nullable|numeric|min:0|max:999999.99',
            'notes' => 'nullable|string|max:1000',
            'plant_codenames' => 'nullable|string|max:2000',
        ]);
    }

    private function plantCodenames(?string $input): ?array
    {
        $codenames = collect(preg_split('/[\r\n,]+/', $input ?? ''))
            ->map(fn (string $codename) => trim($codename))
            ->filter()
            ->unique()
            ->take(100)
            ->values()
            ->all();

        return $codenames ?: null;
    }
    private function farmData(Request $request): array
    {
        return $request->validate([
            'farm_name' => 'required|string|max:120',
            'barangay' => 'nullable|string|max:120',
            'municipality' => 'required|string|max:120',
            'province' => 'required|string|max:120',
            'total_area_hectares' => 'nullable|numeric|min:0|max:999999.99',
            'primary_varieties' => 'nullable|string|max:1000',
        ]);
    }
    private function profile(Request $request): FarmProfile
    {
        return FarmProfile::query()->firstOrCreate(['managed_by' => $request->user()->id], [
            'farm_name' => 'BananaShield Farm',
            'municipality' => 'Selected Municipality',
            'province' => 'Davao del Sur',
            'notification_email' => $request->user()->email,
            'notification_preferences' => self::DEFAULT_NOTIFICATIONS,
            'managed_by' => $request->user()->id,
        ]);
    }
}
