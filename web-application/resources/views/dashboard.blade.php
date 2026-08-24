@extends('layouts.app')
@section('title', 'Dashboard - BananaShield')
@section('content')
<div class="hero-bar">
    <div><p class="eyebrow">{{ ucwords(str_replace('_', ' ', $user->role)) }} workspace</p><h1 class="page-title">Good day, {{ explode(' ', trim($user->name))[0] }}.</h1><p class="page-copy">{{ $user->role === 'farm_owner' ? 'Review submitted farm cases, follow-up records, advisories, and operational summaries.' : ($user->role === 'monitoring_personnel' ? 'Capture plant images, submit farm cases, and record follow-up observations.' : 'Maintain BananaShield users, supported content, model settings, and activity records.') }}</p></div>
    @if($user->role === 'monitoring_personnel')<a class="btn btn-primary" href="{{ route('screenings.create') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>Start new screening</a>@elseif($user->role === 'farm_owner')<a class="btn btn-primary" href="{{ route('monitoring') }}">Review farm cases</a>@else<a class="btn btn-primary" href="{{ route('admin.index') }}">Open administration</a>@endif
</div>

@if($user->isOperational())
@php
    $allCasesUrl = route('monitoring');
    $statusCardUrl = $user->role === 'farm_owner'
        ? route('monitoring', ['review' => 'pending'])
        : ($latestCase ? route('cases.show', $latestCase) : $allCasesUrl);
@endphp
<section class="dashboard-grid" aria-label="Farm activity overview">
    @if($user->role === 'monitoring_personnel')
        <article class="card feature-card"><span class="feature-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20c8 0 15-5 16-16-8 1-15 8-16 16Z"/></svg></span><h2>Guided visual screening</h2><p>Choose Leaf or Whole-Plant capture guidance. Images from both paths are processed by the same four-class model.</p><a class="btn btn-gold" href="{{ route('screenings.create') }}">Begin screening <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14M14 7l5 5-5 5"/></svg></a></article>
    @else
        <article class="card feature-card"><span class="feature-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg></span><h2>Case review and monitoring</h2><p>Review reports submitted by Monitoring Personnel, follow-up histories, preliminary outputs, and cases needing attention.</p><a class="btn btn-gold" href="{{ route('monitoring') }}">Review recorded cases <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14M14 7l5 5-5 5"/></svg></a></article>
    @endif
    <a class="card stat-card stat-card-link" href="{{ $allCasesUrl }}" aria-label="{{ $user->role === 'farm_owner' ? 'Open all '.number_format($caseCount).' recorded farm cases' : 'Open your '.number_format($caseCount).' submitted reports' }}"><span class="stat-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M5 3h14v18H5zM8 7h8M8 11h8"/></svg></span><span class="stat-value">{{ number_format($caseCount) }}</span><span class="stat-label">{{ $user->role === 'farm_owner' ? \Illuminate\Support\Str::plural('Farm case', $caseCount).' recorded' : \Illuminate\Support\Str::plural('Report', $caseCount).' you submitted' }}</span><span class="stat-card-action" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14M14 7l5 5-5 5"/></svg></span></a>
    <a class="card stat-card stat-card-link" href="{{ $statusCardUrl }}" aria-label="{{ $user->role === 'farm_owner' ? 'Open '.number_format($pendingReviewCount).' cases awaiting owner review' : ($latestCase ? 'Open your latest case with status '.ucwords($latestCase->status) : 'Open your submitted reports') }}"><span class="stat-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 4v16h16M7 15l4-4 3 2 5-6"/></svg></span><span class="stat-value">{{ $user->role === 'farm_owner' ? number_format($pendingReviewCount) : ($latestCase?->status ? ucwords($latestCase->status) : '-') }}</span><span class="stat-label">{{ $user->role === 'farm_owner' ? \Illuminate\Support\Str::plural('Case', $pendingReviewCount).' awaiting owner review' : 'Latest case status' }}</span><span class="stat-card-action" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14M14 7l5 5-5 5"/></svg></span></a>
</section>

@if($user->role === 'farm_owner')
<section class="card farm-profile-strip">
    <span class="stat-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 20h18M5 20V9l7-5 7 5v11M9 20v-6h6v6"/></svg></span>
    <div><p class="eyebrow">Farm profile</p><h2>{{ $farmProfile?->farm_name ?? 'Set up the selected farm' }}</h2><p>{{ $farmProfile ? collect([$farmProfile->municipality, $farmProfile->province])->filter()->implode(', ').' · '.$farmProfile->active_sections_count.' active sections' : 'Add farm details, sections or areas, and notification preferences.' }}</p></div>
    <a class="btn btn-secondary" href="{{ route('farm-settings.index') }}">Manage farm settings</a>
</section>
@endif

<div class="section-head"><h2>Recent farm records</h2><a href="{{ route('monitoring') }}">View all cases</a></div>
@if($recentCases->isEmpty())
    <section class="card empty-state"><span class="empty-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 5h16v14H4zM8 9h8"/></svg></span><h3>No cases recorded yet</h3><p>{{ $user->role === 'farm_owner' ? 'Cases submitted by Monitoring Personnel will appear here for review.' : 'A completed screening will be saved as a farm case and appear here.' }}</p>@if($user->role === 'monitoring_personnel')<a class="btn btn-secondary" style="margin-top:18px" href="{{ route('screenings.sample') }}">View sample result</a>@endif</section>
@else
    <section class="card data-table-wrap"><table class="data-table"><thead><tr><th>Case</th><th>Observed</th><th>Preliminary result</th><th>Status</th><th>Review</th></tr></thead><tbody>@foreach($recentCases as $case)<tr><td><a href="{{ route('cases.show',$case) }}">{{ $case->case_number }}</a></td><td>{{ $case->observed_at->format('M j, Y') }}</td><td>{{ $case->latestPrediction?->display_label ?? 'Unavailable' }}</td><td>{{ ucwords($case->status) }}</td><td>{{ ucwords(str_replace('_',' ',$case->review_status)) }}</td></tr>@endforeach</tbody></table></section>
@endif
@else
<section class="card card-pad"><span class="status-pill">System Administrator</span><h2 class="admin-lead">Administration and maintenance</h2><p class="page-copy">Manage authorized users, active disease categories, versioned advisory content, the single active model registry entry, confidence thresholds, and audit logs. Historical screening results are preserved when settings change.</p><a class="btn btn-secondary" href="{{ route('admin.index') }}">Manage system records</a></section>
@endif
@endsection
