<?php

namespace App\Http\Controllers;

use App\Models\FarmProfile;
use App\Models\FarmSection;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FarmSettingsController extends Controller
{
    private const DEFAULT_NOTIFICATIONS = [
        'case_updates' => true,
        'referral_alerts' => true,
        'weekly_summary' => false,
    ];

    public function index(Request $request)
    {
        $profile = $this->profile($request);
        $profile->load('sections');

        return view('farm-settings.index', compact('profile'));
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

        return back()->with('success', 'Farm profile and notification preferences updated.');
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
        ]);

        $section = $profile->sections()->create($data + ['active' => true]);
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
            'active' => 'required|boolean',
        ]);

        $section->update([
            'name' => $data['name'],
            'area_hectares' => $data['area_hectares'] ?? null,
            'notes' => $data['notes'] ?? null,
            'active' => (bool) $data['active'],
        ]);
        $audit->record('farm.section_updated', $section, metadata: ['active' => $section->active]);

        return back()->with('success', 'Farm section updated.');
    }

    private function profile(Request $request): FarmProfile
    {
        return FarmProfile::query()->firstOrCreate([], [
            'farm_name' => 'BananaShield Farm',
            'municipality' => 'Selected Municipality',
            'province' => 'Davao del Sur',
            'notification_email' => $request->user()->email,
            'notification_preferences' => self::DEFAULT_NOTIFICATIONS,
            'managed_by' => $request->user()->id,
        ]);
    }
}
