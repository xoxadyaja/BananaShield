@extends('layouts.app')
@section('title','Operational analytics - BananaShield')

@php
    $trendValues = array_column($analytics['trend'], 'value');
    $trendMax = max(1, ...$trendValues);
    $trendTotal = array_sum($trendValues);
    $chartWidth = 640;
    $chartHeight = 220;
    $chartLeft = 28;
    $chartRight = 20;
    $chartTop = 20;
    $chartBottom = 36;
    $chartFloor = $chartHeight - $chartBottom;
    $plotWidth = $chartWidth - $chartLeft - $chartRight;
    $plotHeight = $chartFloor - $chartTop;
    $trendPoints = [];
    foreach ($analytics['trend'] as $index => $month) {
        $x = $chartLeft + (($plotWidth / max(1, count($analytics['trend']) - 1)) * $index);
        $y = $chartFloor - (($month['value'] / $trendMax) * $plotHeight);
        $trendPoints[] = ['x' => round($x, 2), 'y' => round($y, 2), 'month' => $month];
    }
    $linePoints = collect($trendPoints)->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
    $areaPoints = $chartLeft.','.$chartFloor.' '.$linePoints.' '.($chartWidth - $chartRight).','.$chartFloor;

    $pathTotal = array_sum($analytics['paths']);
    $leafShare = $pathTotal ? round(($analytics['paths']['leaf'] / $pathTotal) * 100, 1) : 0;
    $classTotal = array_sum($analytics['classes']);
    $classMax = max(1, ...array_values($analytics['classes']));
    $statusTotal = array_sum($analytics['statuses']);
    $statusTones = ['open' => 'forest', 'improving' => 'leaf', 'unchanged' => 'slate', 'worsening' => 'danger', 'referred' => 'gold', 'closed' => 'soil'];
@endphp

@section('content')
<div class="hero-bar analytics-hero">
    <div>
        <p class="eyebrow">Analytics Dashboard</p>
        <h1 class="page-title">Farm activity at a glance.</h1>
        <p class="page-copy">Track submitted cases, preliminary outputs, capture paths, and follow-up status across BananaShield.</p>
    </div>
    <span class="period-pill"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 3v3M18 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg>Updated {{ now()->format('M j, Y') }}</span>
</div>

<div class="analytics-warning">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
    <div><strong>Operational records only.</strong> These summaries represent BananaShield submissions and are not official incidence, prevalence, outbreak, or epidemiological-surveillance statistics.</div>
</div>

<section class="metric-grid" aria-label="Key analytics metrics">
    @foreach($analytics['metrics'] as $metric)
        <a class="card metric-card metric-card-link metric-card-{{ $metric['tone'] }}" href="{{ $metric['href'] }}" aria-label="{{ $metric['cta'] }}: {{ number_format($metric['value']) }}">
            <div class="metric-card-top">
                <span>{{ $metric['label'] }}</span>
                <span class="metric-symbol" aria-hidden="true">
                    @switch($loop->index)
                        @case(0)<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 4h14v16H5zM8 8h8M8 12h6M8 16h4"/></svg>@break
                        @case(1)<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>@break
                        @case(2)<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>@break
                        @default<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 20V5h10l-1 4 1 4H5M5 3v18"/></svg>
                    @endswitch
                </span>
            </div>
            <strong>{{ number_format($metric['value']) }}</strong>
            <small>{{ $metric['hint'] }}</small>
            <span class="metric-card-cta">{{ $metric['cta'] }}<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg></span>
        </a>
    @endforeach
</section>

<section class="analytics-primary-grid">
    <article class="card chart-card trend-card">
        <div class="chart-heading">
            <div><p class="chart-kicker">Last six months</p><h2>Submission activity</h2></div>
            <span class="chart-total"><b>{{ $trendTotal }}</b> cases</span>
        </div>
        <figure class="trend-figure">
            <svg class="trend-chart" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="Monthly case submissions over the last six months">
                <defs>
                    <linearGradient id="trend-area" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#3e8b60" stop-opacity=".3"/>
                        <stop offset="100%" stop-color="#3e8b60" stop-opacity=".02"/>
                    </linearGradient>
                </defs>
                @foreach([20, 61, 102, 143, 184] as $gridY)
                    <line class="trend-grid-line" x1="{{ $chartLeft }}" y1="{{ $gridY }}" x2="{{ $chartWidth - $chartRight }}" y2="{{ $gridY }}"/>
                @endforeach
                <polygon class="trend-area" points="{{ $areaPoints }}"/>
                <polyline class="trend-line" points="{{ $linePoints }}"/>
                @foreach($trendPoints as $point)
                    <g class="trend-point">
                        <title>{{ $point['month']['full_label'] }}: {{ $point['month']['value'] }} submissions</title>
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5"/>
                        @if($point['month']['value'] > 0)<text x="{{ $point['x'] }}" y="{{ max(14, $point['y'] - 12) }}" text-anchor="middle">{{ $point['month']['value'] }}</text>@endif
                    </g>
                @endforeach
            </svg>
            <figcaption class="trend-labels">
                @foreach($analytics['trend'] as $month)<span title="{{ $month['full_label'] }}">{{ $month['label'] }}</span>@endforeach
            </figcaption>
            @if($trendTotal === 0)<p class="chart-empty-note">New submissions will appear here as a monthly trend.</p>@endif
        </figure>
    </article>

    <article class="card chart-card capture-card">
        <div class="chart-heading">
            <div><p class="chart-kicker">Image workflow</p><h2>Capture path mix</h2></div>
        </div>
        <div class="donut-layout">
            <div class="donut-chart" role="img" aria-label="{{ $pathTotal ? $leafShare.' percent leaf screening and '.(100 - $leafShare).' percent whole-plant screening' : 'No capture path data yet' }}" style="--donut-fill: {{ $pathTotal ? 'conic-gradient(#2f7650 0 '.$leafShare.'%, #e5ab3d '.$leafShare.'% 100%)' : '#edf0ec' }}">
                <div><strong>{{ $pathTotal }}</strong><span>total cases</span></div>
            </div>
            <div class="chart-legend capture-legend">
                @foreach($analytics['paths'] as $label => $value)
                    @php
                        $share = $pathTotal ? round(($value / $pathTotal) * 100) : 0;
                    @endphp
                    <div class="legend-row">
                        <span class="legend-dot {{ $loop->first ? 'legend-leaf' : 'legend-gold' }}"></span>
                        <div><b>{{ ucwords(str_replace('_', ' ', $label)) }}</b><small>{{ $share }}% of submissions</small></div>
                        <strong>{{ $value }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    </article>
</section>

<section class="analytics-secondary-grid">
    <article class="card chart-card class-chart-card">
        <div class="chart-heading">
            <div><p class="chart-kicker">Preliminary model output</p><h2>Class distribution</h2></div>
            <span class="chart-total"><b>{{ $classTotal }}</b> predictions</span>
        </div>
        <div class="analytics-bars" aria-label="Preliminary class distribution">
            @foreach($analytics['classes'] as $label => $value)
                @php
                    $percent = $classTotal ? round(($value / $classTotal) * 100) : 0;
                @endphp
                <div class="analytics-bar-row tone-{{ ($loop->index % 5) + 1 }}">
                    <div class="analytics-bar-label"><span>{{ $label }}</span><strong>{{ $value }} <small>{{ $percent }}%</small></strong></div>
                    <div class="analytics-bar-track" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $classMax }}" aria-valuenow="{{ $value }}"><i style="width: {{ $value ? max(6, ($value / $classMax) * 100) : 0 }}%"></i></div>
                </div>
            @endforeach
        </div>
    </article>

    <article class="card chart-card workflow-card">
        <div class="chart-heading">
            <div><p class="chart-kicker">Current workflow</p><h2>Case status</h2></div>
            <span class="chart-total"><b>{{ $statusTotal }}</b> cases</span>
        </div>
        <div class="status-stack" role="img" aria-label="Distribution of current case statuses">
            @if($statusTotal)
                @foreach($analytics['statuses'] as $label => $value)
                    @if($value > 0)<span class="status-segment status-{{ $statusTones[$label] ?? 'slate' }}" style="width: {{ ($value / $statusTotal) * 100 }}%" title="{{ ucwords($label) }}: {{ $value }}"></span>@endif
                @endforeach
            @else
                <span class="status-segment status-empty" style="width:100%"></span>
            @endif
        </div>
        <div class="status-legend">
            @foreach($analytics['statuses'] as $label => $value)
                @php
                    $share = $statusTotal ? round(($value / $statusTotal) * 100) : 0;
                @endphp
                <div><span class="legend-dot status-{{ $statusTones[$label] ?? 'slate' }}"></span><p><b>{{ ucwords($label) }}</b><small>{{ $value }} case{{ $value === 1 ? '' : 's' }} · {{ $share }}%</small></p></div>
            @endforeach
        </div>
    </article>
</section>

<p class="analytics-footnote">Charts update automatically from saved BananaShield records. Preliminary classifications and user-entered statuses must not be interpreted as confirmed diagnoses or official disease statistics.</p>
@endsection
