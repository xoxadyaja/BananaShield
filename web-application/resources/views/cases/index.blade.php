@extends('layouts.app')
@section('title', 'Reports - BananaShield')
@section('content')
@php
    $isFiltered = $selectedFarm !== '' || $selectedOutcome !== '' || $search !== '' || $selectedBlock !== '' || $selectedStatus !== '' || $selectedReview !== '' || $selectedDecision !== '';
    $selectedBlockLabel = $selectedBlock === '__unassigned'
        ? 'Unassigned block'
        : ($blocks->firstWhere('query', $selectedBlock)['name'] ?? $selectedBlock);
    $outcomeLabels = ['healthy' => 'Healthy screenings', 'disease' => 'Disease indications', 'inconclusive' => 'Inconclusive results', 'unavailable' => 'Results unavailable'];
    $selectedFarmName = $selectedFarm === '__unassigned' ? 'Farm not assigned' : $farms->firstWhere('id', $selectedFarm)?->farm_name;
@endphp

<div class="hero-bar monitoring-hero">
    <div>
        <h1 class="page-title">Monitor reports by farm block.</h1>
        <p class="page-copy">{{ auth()->user()->role === 'farm_owner' ? 'Filter reports by farm block, personnel, tree codename, diagnosis, case number, or status.' : 'Filter the reports you submitted by farm block, tree codename, case details, or status.' }}</p>
    </div>
    <div class="hero-actions monitoring-hero-actions">
        <div class="report-total" aria-label="{{ number_format($recordTotal) }} {{ auth()->user()->role === 'farm_owner' ? \Illuminate\Support\Str::plural('farm case', $recordTotal) : \Illuminate\Support\Str::plural('submitted report', $recordTotal) }}">
            <span class="report-total-icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg></span>
            <span class="report-total-copy"><small>{{ auth()->user()->role === 'farm_owner' ? 'Farm records' : 'Your activity' }}</small><span><strong>{{ number_format($recordTotal) }}</strong> {{ auth()->user()->role === 'farm_owner' ? \Illuminate\Support\Str::plural('case', $recordTotal) : \Illuminate\Support\Str::plural('submitted report', $recordTotal) }}</span></span>
        </div>
        @if(auth()->user()->canSubmitScreenings())
            <a class="btn btn-primary new-report-button" href="{{ route('screenings.create') }}">
                <span class="new-report-icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg></span>
                <span class="new-report-copy"><strong>New farm report</strong><small>Start a guided screening</small></span>
            </a>
        @endif
    </div>
</div>

<form class="card report-search" method="GET" action="{{ route('monitoring') }}" role="search" aria-label="Search and filter reports">
    @if($selectedReview !== '')<input type="hidden" name="review" value="{{ $selectedReview }}">@endif
    @if($selectedDecision !== '')<input type="hidden" name="decision" value="{{ $selectedDecision }}">@endif
    <div class="report-search-main">
        <label class="report-search-field">
            <span class="report-filter-label">Search reports</span>
            <span class="report-search-input-wrap">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
                <input type="search" name="q" value="{{ $search }}" maxlength="120" placeholder="Case number, plant codename, reporter..." autocomplete="off">
            </span>
        </label>
        <div class="report-search-actions">
            <button class="btn btn-primary" type="submit">Search reports<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg></button>
            @if($isFiltered)<a class="report-clear-link" href="{{ route('monitoring') }}">Clear filters</a>@endif
        </div>
    </div>
    <div class="report-filter-row">
    <label class="report-block-filter report-farm-filter">
        <span class="report-filter-label">Farm</span>
        <select name="farm">
            <option value="">All farms</option>
            @foreach($farms as $farm)
                <option value="{{ $farm->id }}" @selected((string) $farm->id === $selectedFarm)>{{ $farm->farm_name }}</option>
            @endforeach
            <option value="__unassigned" @selected($selectedFarm === '__unassigned')>Farm not assigned</option>
        </select>
    </label>
    <label class="report-block-filter">
        <span class="report-filter-label">Block</span>
        <select name="block">
            <option value="">All farm blocks</option>
            @foreach($blocks as $block)
                <option value="{{ $block['query'] }}" @selected(\Illuminate\Support\Str::lower($selectedBlock) === \Illuminate\Support\Str::lower($block['query']))>{{ $block['name'] }} ({{ $block['reports_count'] }})</option>
            @endforeach
        </select>
    </label>
    <label class="report-block-filter report-outcome-filter">
        <span class="report-filter-label">Result</span>
        <select name="outcome">
            <option value="">All results</option>
            @foreach($outcomeLabels as $key => $label)
                <option value="{{ $key }}" @selected($selectedOutcome === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="report-status-filter">
        <span class="report-filter-label">Status</span>
        <select name="status">
            <option value="">All statuses</option>
            @foreach(['open', 'improving', 'unchanged', 'worsening', 'referred', 'closed'] as $status)
                <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ ucwords($status) }}</option>
            @endforeach
        </select>
    </label>
    </div>
    @if($selectedReview !== '' || $selectedDecision !== '')
        <div class="report-active-filters">
            @if($selectedReview !== '')<span>Owner review: <strong>{{ ucwords(str_replace('_', ' ', $selectedReview)) }}</strong></span>@endif
            @if($selectedDecision !== '')<span>Decision: <strong>{{ ucfirst($selectedDecision) }}</strong></span>@endif
        </div>
    @endif
</form>

<section class="report-results" aria-labelledby="report-results-heading">
    <div class="report-results-head">
        <div>
            <h2 id="report-results-heading">{{ $outcomeLabels[$selectedOutcome] ?? ($selectedReview === 'pending' ? 'Cases awaiting owner review' : ($selectedDecision === 'inconclusive' ? 'Inconclusive results' : ($selectedDecision === 'conclusive' ? 'Conclusive results' : ($selectedBlock !== '' ? $selectedBlockLabel.' reports' : 'Recorded reports')))) }}</h2>
            @if($selectedFarmName)<p>Farm: <strong>{{ $selectedFarmName }}</strong></p>@endif
            <p>{{ number_format($cases->total()) }} {{ \Illuminate\Support\Str::plural('case', $cases->total()) }} {{ $isFiltered ? 'match the current filters' : 'available for review' }}.</p>
        </div>
        @if($selectedBlock !== '')<a href="{{ route('monitoring', array_filter(['farm' => $selectedFarm, 'outcome' => $selectedOutcome, 'q' => $search, 'status' => $selectedStatus, 'review' => $selectedReview, 'decision' => $selectedDecision])) }}">View every block</a>@endif
    </div>

    @if($cases->isEmpty())
        <div class="card empty-state report-empty-state">
            <span class="empty-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19h16M6 19V9l6-5 6 5v10M9 13h6"/></svg></span>
            @if($recordTotal === 0)
                <h3>No farm cases recorded yet</h3>
                <p>Complete a guided screening to create the first report and assign it to a farm block.</p>
            @elseif($selectedBlock !== '' && $selectedOutcome === '' && $search === '' && $selectedStatus === '' && $selectedReview === '' && $selectedDecision === '')
                <h3>No reports in {{ $selectedBlockLabel }}</h3>
                <p>This block is ready, but no recorded farm case has been assigned to it yet.</p>
            @else
                <h3>No reports match these filters</h3>
                <p>Try another reporter, block, case number, diagnosis, or status.</p>
                <a class="btn btn-secondary" href="{{ route('monitoring') }}">Clear all filters</a>
            @endif
        </div>
    @else
        <div class="report-record-list">
            @foreach($cases as $case)
                <article class="report-list-item" aria-labelledby="report-title-{{ $case->id }}">
                    <span class="report-list-icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 3h9l3 3v15H6zM14 3v4h4M9 12h6M9 16h6"/></svg></span>
                    <div class="report-list-primary">
                        <h3 id="report-title-{{ $case->id }}">{{ $case->latestPrediction?->display_label ?? 'Result unavailable' }}</h3>
                        <p class="report-list-location"><strong>{{ $case->case_number }}</strong><span>Farm: {{ $case->farmProfile?->farm_name ?: 'Not assigned' }}</span><span>{{ $case->farm_section ?: 'Unassigned block' }}</span><span>{{ $case->tree_codename ? 'Tree '.$case->tree_codename : 'Tree codename not provided' }}</span></p>
                        <dl class="report-list-details">
                            <div><dt>Observed</dt><dd>{{ $case->observed_at->format('M j, Y') }}</dd></div>
                            <div><dt>Submitted by</dt><dd>{{ $case->submitter->name }}</dd></div>
                            <div><dt>Owner review</dt><dd>{{ ucwords(str_replace('_', ' ', $case->review_status)) }}</dd></div>
                            <div><dt>Follow-ups</dt><dd>{{ $case->follow_ups_count }}</dd></div>
                        </dl>
                        <p class="report-list-meta"><span>{{ $case->variety ?: 'Variety not provided' }}</span><span>{{ ucwords(str_replace('_', ' ', $case->screening_path)) }}</span><span>{{ round(($case->latestPrediction?->confidence ?? 0) * 100) }}% probability</span></p>
                    </div>
                    <div class="report-list-actions">
                        <span class="case-status {{ in_array($case->status, ['worsening', 'referred']) ? 'danger' : (in_array($case->status, ['improving', 'closed']) ? 'success' : 'warning') }}">{{ ucwords($case->status) }}</span>
                        <a class="report-list-open" href="{{ route('cases.show', $case) }}" aria-label="Open case history for {{ $case->case_number }}"><span>Open case history</span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></a>
                        <a class="report-list-advisory" href="{{ route('advisories', ['condition' => $case->latestPrediction?->predicted_class]) }}">View advisory</a>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="pagination-wrap">{{ $cases->links() }}</div>
    @endif
</section>
@endsection
