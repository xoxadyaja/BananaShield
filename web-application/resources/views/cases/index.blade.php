@extends('layouts.app')
@section('title', 'Monitoring and reports - BananaShield')
@section('content')
@php
    $isFiltered = $search !== '' || $selectedBlock !== '' || $selectedStatus !== '' || $selectedReview !== '' || $selectedDecision !== '';
    $selectedBlockLabel = $selectedBlock === '__unassigned'
        ? 'Unassigned block'
        : ($blocks->firstWhere('query', $selectedBlock)['name'] ?? $selectedBlock);
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

<div class="analytics-warning">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
    <div><strong>Farm records only.</strong> Status updates are user observations and do not confirm disease progression or treatment effectiveness.</div>
</div>

<form class="card report-search" method="GET" action="{{ route('monitoring') }}" role="search">
    @if($selectedReview !== '')<input type="hidden" name="review" value="{{ $selectedReview }}">@endif
    @if($selectedDecision !== '')<input type="hidden" name="decision" value="{{ $selectedDecision }}">@endif
    <label class="report-search-field">
        <span class="sr-only">Search reports</span>
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
        <input type="search" name="q" value="{{ $search }}" maxlength="120" placeholder="Search reporter, tree codename, block, case number…" autocomplete="off">
    </label>
    <label class="report-block-filter">
        <span class="sr-only">Filter by farm block</span>
        <select name="block">
            <option value="">All farm blocks</option>
            @foreach($blocks as $block)
                <option value="{{ $block['query'] }}" @selected(\Illuminate\Support\Str::lower($selectedBlock) === \Illuminate\Support\Str::lower($block['query']))>{{ $block['name'] }} ({{ $block['reports_count'] }})</option>
            @endforeach
        </select>
    </label>
    <label class="report-status-filter">
        <span class="sr-only">Filter by case status</span>
        <select name="status">
            <option value="">All statuses</option>
            @foreach(['open', 'improving', 'unchanged', 'worsening', 'referred', 'closed'] as $status)
                <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ ucwords($status) }}</option>
            @endforeach
        </select>
    </label>
    <button class="btn btn-primary" type="submit">Search reports</button>
    @if($isFiltered)<a class="report-clear-link" href="{{ route('monitoring') }}">Clear filters</a>@endif
</form>

<section class="report-results" aria-labelledby="report-results-heading">
    <div class="report-results-head">
        <div>
            <h2 id="report-results-heading">{{ $selectedReview === 'pending' ? 'Cases awaiting owner review' : ($selectedDecision === 'inconclusive' ? 'Inconclusive results' : ($selectedDecision === 'conclusive' ? 'Conclusive results' : ($selectedBlock !== '' ? $selectedBlockLabel.' reports' : 'Recorded reports'))) }}</h2>
            <p>{{ number_format($cases->total()) }} {{ \Illuminate\Support\Str::plural('case', $cases->total()) }} {{ $isFiltered ? 'match the current filters' : 'available for review' }}.</p>
        </div>
        @if($selectedBlock !== '')<a href="{{ route('monitoring', array_filter(['q' => $search, 'status' => $selectedStatus, 'review' => $selectedReview, 'decision' => $selectedDecision])) }}">View every block</a>@endif
    </div>

    @if($cases->isEmpty())
        <div class="card empty-state report-empty-state">
            <span class="empty-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19h16M6 19V9l6-5 6 5v10M9 13h6"/></svg></span>
            @if($recordTotal === 0)
                <h3>No farm cases recorded yet</h3>
                <p>Complete a guided screening to create the first report and assign it to a farm block.</p>
            @elseif($selectedBlock !== '' && $search === '' && $selectedStatus === '' && $selectedReview === '' && $selectedDecision === '')
                <h3>No reports in {{ $selectedBlockLabel }}</h3>
                <p>This block is ready, but no recorded farm case has been assigned to it yet.</p>
            @else
                <h3>No reports match these filters</h3>
                <p>Try another reporter, block, case number, diagnosis, or status.</p>
                <a class="btn btn-secondary" href="{{ route('monitoring') }}">Clear all filters</a>
            @endif
        </div>
    @else
        <div class="case-card-grid">
            @foreach($cases as $case)
                <article class="card monitor-card">
                    <div class="monitor-top">
                        <div><span class="case-number">{{ $case->case_number }}</span><h3>{{ $case->latestPrediction?->display_label ?? 'Result unavailable' }}</h3></div>
                        <span class="case-status {{ in_array($case->status, ['worsening', 'referred']) ? 'danger' : (in_array($case->status, ['improving', 'closed']) ? 'success' : 'warning') }}">{{ ucwords($case->status) }}</span>
                    </div>
                    <div class="case-block-label">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19h16M6 19V9l6-5 6 5v10M9 13h6"/></svg>
                        <span class="case-location-copy"><strong>{{ $case->farm_section ?: 'Unassigned block' }}</strong><small>{{ $case->tree_codename ? 'Tree '.$case->tree_codename : 'Tree codename not provided' }}</small></span>
                    </div>
                    <div class="monitor-meta"><span>{{ $case->variety ?: 'Variety not provided' }}</span><span>{{ ucwords(str_replace('_', ' ', $case->screening_path)) }}</span><span>{{ round(($case->latestPrediction?->confidence ?? 0) * 100) }}% confidence</span></div>
                    <dl class="case-summary-list"><div><dt>Observed</dt><dd>{{ $case->observed_at->format('M j, Y') }}</dd></div><div><dt>Submitted by</dt><dd>{{ $case->submitter->name }}</dd></div><div><dt>Owner review</dt><dd>{{ ucwords(str_replace('_', ' ', $case->review_status)) }}</dd></div><div><dt>Follow-ups</dt><dd>{{ $case->follow_ups_count }}</dd></div></dl>
                    <div class="monitor-actions"><a href="{{ route('cases.show', $case) }}">Open case history</a><a href="{{ route('advisories', ['condition' => $case->latestPrediction?->predicted_class]) }}">View advisory</a></div>
                </article>
            @endforeach
        </div>
        <div class="pagination-wrap">{{ $cases->links() }}</div>
    @endif
</section>
@endsection
