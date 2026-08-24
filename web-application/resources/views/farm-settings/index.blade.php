@extends('layouts.app')
@section('title', 'Farm profile and settings - BananaShield')

@php
    $notifications = $profile->notification_preferences ?? [];
@endphp

@section('content')
<div class="hero-bar">
    <div>
        <p class="eyebrow">Farm Owner Input</p>
        <h1 class="page-title">Farm profile and settings.</h1>
        <p class="page-copy">Maintain farm details, sections or areas, and notification preferences used to organize BananaShield records.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('dashboard') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m14 6-6 6 6 6"/></svg>Back to dashboard</a>
</div>

<div class="analytics-warning">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
    <div><strong>Record organization only.</strong> Farm details and section labels provide operational context. They do not establish official boundaries, disease incidence, or regulatory records.</div>
</div>

<section class="settings-grid">
    <form class="card card-pad form-stack farm-profile-form" method="POST" action="{{ route('farm-settings.update') }}">
        @csrf @method('PATCH')
        <div class="settings-card-head">
            <span class="settings-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 20h18M5 20V9l7-5 7 5v11M9 20v-6h6v6"/></svg></span>
            <div><h2>Farm details</h2><p>Information displayed as context for farm-level records.</p></div>
        </div>
        <label class="field"><span class="field-label">Farm name</span><input required class="input" name="farm_name" value="{{ old('farm_name', $profile->farm_name) }}" maxlength="120"></label>
        <div class="field-grid">
            <label class="field"><span class="field-label">Barangay</span><input class="input" name="barangay" value="{{ old('barangay', $profile->barangay) }}" maxlength="120"></label>
            <label class="field"><span class="field-label">Municipality or city</span><input required class="input" name="municipality" value="{{ old('municipality', $profile->municipality) }}" maxlength="120"></label>
            <label class="field"><span class="field-label">Province</span><input required class="input" name="province" value="{{ old('province', $profile->province) }}" maxlength="120"></label>
            <label class="field"><span class="field-label">Total area <small>hectares</small></span><input type="number" min="0" max="999999.99" step="0.01" class="input" name="total_area_hectares" value="{{ old('total_area_hectares', $profile->total_area_hectares) }}"></label>
        </div>
        <label class="field"><span class="field-label">Primary banana varieties <small>Optional</small></span><textarea class="input" name="primary_varieties" maxlength="1000" placeholder="Example: Cardava, Tundan">{{ old('primary_varieties', $profile->primary_varieties) }}</textarea></label>

        <div class="settings-divider"></div>
        <div class="settings-card-head settings-card-head-small">
            <span class="settings-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></span>
            <div><h2>Notification preferences</h2><p>Store how the Farm Owner wants operational updates summarized.</p></div>
        </div>
        <label class="field"><span class="field-label">Notification email <small>Optional</small></span><input type="email" class="input" name="notification_email" value="{{ old('notification_email', $profile->notification_email) }}" maxlength="255"></label>
        <div class="preference-grid">
            @foreach([
                'case_updates' => ['Case updates', 'Changes to recorded case status and follow-ups.'],
                'referral_alerts' => ['Referral alerts', 'Cases marked for qualified agricultural assessment.'],
                'weekly_summary' => ['Weekly summary', 'A preference for periodic system-recorded summaries.'],
            ] as $key => [$label, $help])
                <label class="preference-option">
                    <input type="hidden" name="{{ $key }}" value="0">
                    <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $notifications[$key] ?? false))>
                    <span><b>{{ $label }}</b><small>{{ $help }}</small></span>
                </label>
            @endforeach
        </div>
        <button class="btn btn-primary" type="submit">Save farm profile</button>
    </form>

    <aside class="card card-pad settings-summary">
        <p class="eyebrow">Profile summary</p>
        <h2>{{ $profile->farm_name }}</h2>
        <dl class="settings-summary-list">
            <div><dt>Location</dt><dd>{{ collect([$profile->barangay, $profile->municipality, $profile->province])->filter()->implode(', ') ?: 'Not provided' }}</dd></div>
            <div><dt>Recorded area</dt><dd>{{ $profile->total_area_hectares !== null ? number_format((float) $profile->total_area_hectares, 2).' ha' : 'Not provided' }}</dd></div>
            <div><dt>Active sections</dt><dd>{{ $profile->sections->where('active', true)->count() }}</dd></div>
            <div><dt>Last updated</dt><dd>{{ $profile->updated_at?->format('M j, Y') ?? 'Not yet updated' }}</dd></div>
        </dl>
        <div class="notice notice-green"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20c8 0 15-5 16-16-8 1-15 8-16 16Z"/></svg><span>Section names can be used as consistent farm-area references when recording screening context.</span></div>
    </aside>
</section>

<section class="card card-pad farm-sections-card">
    <div class="section-head settings-section-head"><div><p class="eyebrow">Farm areas</p><h2>Sections and blocks</h2></div><span class="field-help">{{ $profile->sections->count() }} recorded</span></div>
    <div class="section-manager-grid">
        <div class="section-list">
            @forelse($profile->sections as $section)
                <form class="section-edit-card" method="POST" action="{{ route('farm-settings.sections.update', $section) }}">
                    @csrf @method('PATCH')
                    <div class="section-edit-head"><strong>{{ $section->name }}</strong><span class="case-status {{ $section->active ? 'success' : 'warning' }}">{{ $section->active ? 'Active' : 'Inactive' }}</span></div>
                    <div class="field-grid">
                        <label class="field"><span class="field-label">Section name</span><input required class="input" name="name" value="{{ $section->name }}" maxlength="120"></label>
                        <label class="field"><span class="field-label">Area <small>hectares</small></span><input type="number" min="0" max="999999.99" step="0.01" class="input" name="area_hectares" value="{{ $section->area_hectares }}"></label>
                    </div>
                    <label class="field"><span class="field-label">Operational notes</span><textarea class="input" name="notes" maxlength="1000">{{ $section->notes }}</textarea></label>
                    <label class="field"><span class="field-label">Status</span><select class="input" name="active"><option value="1" @selected($section->active)>Active</option><option value="0" @selected(!$section->active)>Inactive</option></select></label>
                    <button class="btn btn-secondary" type="submit">Update section</button>
                </form>
            @empty
                <div class="empty-state section-empty"><span class="empty-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20V6l5-3 6 3 5-2v14l-5 3-6-3-5 2ZM9 3v15M15 6v15"/></svg></span><h3>No farm sections recorded</h3><p>Add a block, plot, or section to standardize contextual farm-area labels.</p></div>
            @endforelse
        </div>
        <form class="section-create-form form-stack" method="POST" action="{{ route('farm-settings.sections.store') }}">
            @csrf
            <div><p class="eyebrow">New farm area</p><h3>Add a section</h3><p class="field-help">Use the farm's established operational name.</p></div>
            <label class="field"><span class="field-label">Section name</span><input required class="input" name="name" value="{{ old('name') }}" maxlength="120" placeholder="Example: North Block"></label>
            <label class="field"><span class="field-label">Area <small>hectares</small></span><input type="number" min="0" max="999999.99" step="0.01" class="input" name="area_hectares" value="{{ old('area_hectares') }}"></label>
            <label class="field"><span class="field-label">Operational notes <small>Optional</small></span><textarea class="input" name="notes" maxlength="1000" placeholder="Landmark or management notes">{{ old('notes') }}</textarea></label>
            <button class="btn btn-primary" type="submit">Add farm section</button>
        </form>
    </div>
</section>
@endsection
