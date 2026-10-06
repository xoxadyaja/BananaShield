@extends('layouts.app')
@section('title', 'Farm analytics - BananaShield')
@section('content')
@php
    $trendMax = max(1, ...array_column($analytics['trend'], 'total'));
    $trendTotal = array_sum(array_column($analytics['trend'], 'total'));
    $total = $analytics['summary']['total'];
    $outcomes = ['healthy' => 'Healthy', 'disease' => 'Disease indication', 'inconclusive' => 'Inconclusive', 'unavailable' => 'Unavailable'];
@endphp

<div class="hero-bar">
    <div>
        <h1 class="page-title">Farm analytics</h1>
        <p class="page-copy">Review healthy screenings, disease indications, and follow-up activity across your recorded farm reports.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('monitoring', $reportParams) }}">View reports</a>
</div>

<form class="analytics-filter" method="GET" action="{{ route('analytics') }}">
    <label class="field">
        <span class="field-label">Farm</span>
        <select class="input" name="farm">
            <option value="">All farms</option>
            @foreach($farms as $farm)
                <option value="{{ $farm->id }}" @selected((string) $farm->id === $selectedFarm)>{{ $farm->farm_name }}</option>
            @endforeach
            <option value="__unassigned" @selected($selectedFarm === '__unassigned')>Farm not assigned</option>
        </select>
    </label>
    <button class="btn btn-primary" type="submit">Apply filter</button>
    @if($selectedFarm !== '')<a class="analytics-reset" href="{{ route('analytics') }}">Clear filter</a>@endif
    <p class="analytics-scope"><strong>{{ $selectedFarmName }}</strong><span>All-time totals · Updated {{ now()->format('M j, Y') }}</span></p>
</form>

<section class="analytics-summary" aria-label="Recorded screening totals">
    @foreach($analytics['metrics'] as $metric)
        <a class="analytics-summary-item analytics-tone-{{ $metric['tone'] }}" href="{{ $metric['href'] }}">
            <span>{{ $metric['label'] }}</span>
            <strong>{{ number_format($metric['value']) }}</strong>
            <small>{{ $metric['hint'] }}</small>
            <span class="analytics-summary-link">View reports <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg></span>
        </a>
    @endforeach
</section>
@if($analytics['summary']['unavailable'] > 0)
    <p class="analytics-missing">{{ $analytics['summary']['unavailable'] }} recorded screening(s) have no available result and are included in the total. <a href="{{ route('monitoring', $reportParams + ['outcome' => 'unavailable']) }}">View these reports</a></p>
@endif

@if($total === 0)
    <div class="analytics-empty">
        <h2>No screenings recorded{{ $selectedFarm !== '' ? ' for this farm' : '' }} yet</h2>
        <p>Completed screenings, including healthy results, will appear here once monitoring personnel save them.</p>
    </div>
@endif

<div class="analytics-panels">
    <section class="analytics-panel" aria-labelledby="submission-activity-title">
        <header class="analytics-panel-heading">
            <div><h2 id="submission-activity-title">Submission activity</h2><p>Last six months, by date recorded</p></div>
            <span>{{ number_format($trendTotal) }} reports</span>
        </header>
        <div class="analytics-key">
            @foreach($outcomes as $key => $label)<span><i class="outcome-{{ $key }}" aria-hidden="true"></i>{{ $label }}</span>@endforeach
        </div>
        <div class="analytics-months">
            @foreach($analytics['trend'] as $month)
                <div class="analytics-month">
                    <span class="analytics-month-label">{{ $month['label'] }}</span>
                    <div class="analytics-month-track" role="img" aria-label="{{ $month['full_label'] }}: {{ $month['total'] }} reports; {{ $month['healthy'] }} healthy, {{ $month['disease'] }} disease indications, {{ $month['inconclusive'] }} inconclusive, {{ $month['unavailable'] }} unavailable">
                        @foreach($outcomes as $key => $label)
                            @if($month[$key] > 0)<span class="outcome-{{ $key }}" style="width: {{ ($month[$key] / $trendMax) * 100 }}%" title="{{ $label }}: {{ $month[$key] }}"></span>@endif
                        @endforeach
                    </div>
                    <strong>{{ $month['total'] }}</strong>
                </div>
            @endforeach
        </div>
        @if($trendTotal === 0)<p class="analytics-panel-note">No submissions in the last six months.</p>@endif
    </section>

    <section class="analytics-panel" aria-labelledby="class-distribution-title">
        <header class="analytics-panel-heading"><div><h2 id="class-distribution-title">Screening results</h2><p>Latest result per report · All time</p></div></header>
        <div class="analytics-distribution">
            @foreach($analytics['classes'] as $class)
                @if($class['tone'] !== 'unavailable' || $class['value'] > 0)
                    @php $share = $total ? ($class['value'] / $total) * 100 : 0; @endphp
                    <div>
                        <div class="analytics-distribution-label"><span>{{ $class['label'] }}</span><strong>{{ $class['value'] }} <small>{{ round($share, 1) }}%</small></strong></div>
                        <div class="analytics-distribution-track" aria-hidden="true"><span class="outcome-{{ $class['tone'] }}" style="width: {{ $share }}%"></span></div>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
</div>

<section class="analytics-panel analytics-farms" aria-labelledby="farm-breakdown-title">
    <header class="analytics-panel-heading"><div><h2 id="farm-breakdown-title">Reports by farm</h2><p>Compare recorded results, then open a farm's reports.</p></div></header>
    @if($analytics['farmRows']->isEmpty())
        <p class="analytics-panel-note">No registered farms yet. <a href="{{ route('farm-settings.index') }}">Manage farms</a> to register one.</p>
    @else
        <div class="analytics-table-wrap" tabindex="0" role="region" aria-label="Screening counts by farm">
            <table class="analytics-farm-table">
                <thead><tr><th scope="col">Farm</th><th scope="col">Total</th><th scope="col">Healthy</th><th scope="col">Disease indications</th><th scope="col">Inconclusive</th>@if($analytics['summary']['unavailable'])<th scope="col">Unavailable</th>@endif</tr></thead>
                <tbody>
                    @foreach($analytics['farmRows'] as $farm)
                        <tr>
                            <th scope="row"><a href="{{ $farm['href'] }}">{{ $farm['name'] }}<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></a><small>{{ $farm['location'] ?: 'Location not provided' }}</small></th>
                            <td><strong>{{ $farm['total'] }}</strong></td><td>{{ $farm['healthy'] }}</td><td>{{ $farm['disease'] }}</td><td>{{ $farm['inconclusive'] }}</td>@if($analytics['summary']['unavailable'])<td>{{ $farm['unavailable'] }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="analytics-panel analytics-workflow" aria-labelledby="case-status-title">
    <header class="analytics-panel-heading">
        <div><h2 id="case-status-title">Case status</h2><p>User-recorded follow-up status, separate from the screening result</p></div>
        <a href="{{ route('monitoring', $reportParams + ['review' => 'pending']) }}">{{ $analytics['pendingReview'] }} awaiting owner review</a>
    </header>
    <div class="analytics-status-list">
        @foreach($analytics['statuses'] as $status => $count)
            <a href="{{ route('monitoring', $reportParams + ['status' => $status]) }}"><span>{{ ucfirst($status) }}</span><strong>{{ $count }}</strong></a>
        @endforeach
    </div>
</section>
<p class="analytics-panel-note analytics-footnote">These are saved screening records, not unique plant counts or confirmed diagnoses. Repeated screenings count separately. Healthy results are preliminary; recorded status changes do not establish treatment effectiveness.</p>
@endsection
