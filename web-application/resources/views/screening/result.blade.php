@extends('layouts.app')
@section('title', 'Preliminary result - BananaShield')
@section('content')
@if($result['mock_fallback'] ?? false)
<div class="alert alert-success"><span><strong>Transparent mock mode:</strong> this demonstration output was generated locally because a trained, validated model file is not active. The case workflow and safeguards remain functional.</span></div>
@endif
@if($isDemo ?? false)<div class="alert alert-success"><span><strong>Sample result:</strong> this preview was not generated from an upload and was not saved as a case.</span></div>@endif
@if(!($isDemo ?? false) && !$case && ($result['predicted_class'] ?? '') === 'healthy_banana')
<div class="alert alert-success"><span><strong>Healthy screening—not added to reports.</strong> No farm case was created. Only disease or inconclusive results are saved for monitoring and follow-up.</span></div>
@endif

<div class="hero-bar"><div><p class="eyebrow">{{ ($isDemo ?? false) ? 'Demonstration preview' : ($case ? 'Screening complete and case saved' : 'Screening complete') }}</p><h1 class="page-title">Preliminary visual-screening result</h1><p class="page-copy">Interpret the model output together with image quality, confidence, advisory guidance, and the diagnostic limitation.</p></div><a class="btn btn-secondary" href="{{ route('screenings.create') }}">New screening</a></div>

<article class="card result-hero">
    <section class="result-copy">
        <span class="status-pill">{{ ucwords($result['decision_status']) }}</span>
        <p class="eyebrow" style="margin-top:26px">Supported model category</p>
        <h2 class="result-title">{{ $result['display_label'] }}</h2>
        <p class="result-message">{{ $result['message'] }}</p>
        <div class="confidence"><div class="confidence-head"><span>Model confidence</span><strong>{{ round($result['confidence'] * 100) }}%</strong></div><div class="confidence-track"><div class="confidence-fill" style="width:{{ max(0,min(100,round($result['confidence']*100))) }}%"></div></div><p class="field-help" style="margin-top:8px">Configured inconclusive threshold: {{ round($result['confidence_threshold'] * 100) }}%. Confidence is relative model certainty, not proof that disease is present.</p></div>
        <dl class="meta-grid">
            <div class="meta-item"><dt>Main image path</dt><dd>{{ ucwords(str_replace('_',' ',$result['image_path'] ?? $result['screening_path'])) }}</dd></div>
            <div class="meta-item"><dt>Specific view</dt><dd>{{ ucwords(str_replace('_',' ',$result['specific_view'] ?? $result['view_type'])) }}</dd></div>
            <div class="meta-item"><dt>Image quality</dt><dd>{{ ucwords(str_replace('_',' ',$result['quality_status'])) }}</dd></div>
            <div class="meta-item"><dt>Model registry</dt><dd>{{ $result['architecture'] }} - {{ $result['model_version'] }}</dd></div>
        </dl>
        <div class="notice notice-amber" style="margin-top:22px"><span>{{ $result['disclaimer'] }} It is not a severity assessment, laboratory confirmation, or treatment authorization.</span></div>
        @if(!($isDemo ?? false) && $case)<div class="result-case-link"><div><strong>Farm case {{ $case->case_number }}</strong><small>Saved privately for monitoring, review, and follow-up.</small></div><a class="btn btn-primary" href="{{ route('cases.show',$case) }}">Open case record</a></div>@endif
    </section>
    <figure class="result-visual result-photo">
        @if(!($isDemo ?? false) && !$case && ($imagePreview ?? null))
            <img alt="Submitted banana plant image" src="{{ $imagePreview }}">
        @elseif(!($isDemo ?? false) && $case && isset($caseImage))
            <img alt="Submitted banana plant image" src="{{ route('cases.images.show',[$case,$caseImage]) }}">
        @else
            <img alt="Sample banana leaf visual" src="{{ asset('images/banana-field.svg') }}">
        @endif
        <figcaption>{{ !($isDemo ?? false) && !$case ? 'Submitted image shown for this screening only; it was not stored as a farm case.' : 'Submitted image shown for record review. BananaShield does not claim lesion segmentation or infected-area measurement.' }}</figcaption>
    </figure>
</article>

<section class="card card-pad result-explanation"><div class="form-section-title"><span class="stat-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg></span><div><h2>Image-quality reminder</h2><span class="field-help">Lighting, camera angle, distance, background, severity, plant age, and the photographed plant part can affect classification.</span></div></div><p>If the plant view is unclear, symptoms are unsupported, confidence is low, or the plant is worsening, retake the image or seek qualified agricultural assessment or laboratory confirmation.</p></section>

<section class="card result-advisory advisory-{{ $advisory['tone'] }}">
    <header class="result-advisory-head"><div class="advisory-symbol {{ $advisory['tone'] }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg></div><div><p class="eyebrow">Corresponding advisory information</p><h2>{{ $advisory['title'] }}</h2><p>{{ $advisory['summary'] }}</p></div></header>
    <div class="recommendation-grid">
        <article class="recommendation-block recommendation-primary"><span class="recommendation-kicker">General guidance</span><h3>Suggested next steps</h3><ul>@foreach($advisory['guidance'] as $item)<li>{{ $item }}</li>@endforeach</ul></article>
        <article class="recommendation-block"><span class="recommendation-kicker">Prevention</span><h3>Risk-reduction practices</h3><ul>@foreach($advisory['prevention'] as $item)<li>{{ $item }}</li>@endforeach</ul></article>
        <article class="recommendation-block"><span class="recommendation-kicker">Containment</span><h3>Field reminders</h3><ul>@foreach($advisory['containment'] as $item)<li>{{ $item }}</li>@endforeach</ul></article>
    </div>
    <div class="consult-action"><span class="consult-action-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h9a4 4 0 0 1 4 4z"/></svg></span><div><strong>Professional consultation</strong><p>{{ $advisory['consult'] }}</p></div></div>
    <footer class="result-advisory-footer"><p>General educational guidance automatically matched to a preliminary class; not a prescription or confirmed diagnosis.</p><a class="btn btn-secondary" href="{{ route('advisories',['condition'=>$result['predicted_class']]) }}">Open advisory library</a></footer>
</section>
@endsection
