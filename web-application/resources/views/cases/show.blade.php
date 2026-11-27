@extends('layouts.app')
@section('title', $case->case_number.' - BananaShield')
@section('content')
<div class="hero-bar"><div><p class="eyebrow">Farm case history</p><h1 class="page-title">{{ $case->case_number }}</h1><p class="page-copy">Submitted by {{ $case->submitter->name }} for observation on {{ $case->observed_at->format('F j, Y') }}.</p></div><a class="btn btn-secondary" href="{{ route('monitoring') }}">Back to monitoring</a></div>

<section class="case-detail-grid">
<div class="case-detail-main">
    <article class="card card-pad">
        @php
            $prediction = $case->latestPrediction;
            $screeningImage = $case->images->firstWhere('image_type', 'original') ?? $case->images->first();
            $detectedView = $screeningImage?->detected_part
                ?? ucwords(str_replace('_', ' ', $screeningImage?->specific_view ?? $screeningImage?->view_type ?? $case->screening_path));
            $threshold = $prediction?->modelVersion?->confidence_threshold;
        @endphp
        <div class="monitor-top"><div><h2 class="case-result-heading">Saved screening result</h2><p class="field-help">Recorded when this case was submitted. Follow-up updates do not overwrite this result.</p></div><span class="case-status {{ in_array($case->status,['worsening','referred']) ? 'danger' : 'warning' }}">Case {{ ucwords($case->status) }}</span></div>
        @if($prediction)
        <section class="case-screening-record" aria-label="Original screening result">
            <div class="case-result-header">
                <span class="status-pill {{ $prediction->decision_status === 'conclusive' ? 'success' : 'warning' }}">{{ ucwords($prediction->decision_status) }}</span>
                <span class="field-help">Saved {{ $prediction->created_at?->format('M j, Y g:i A') }}</span>
            </div>
            <div class="case-detected-view">
                <span class="detected-view-mark" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20c8 0 15-5 16-16-8 1-15 8-16 16Z"/></svg></span>
                <div><span>Detected image view</span><strong>{{ $detectedView ?: 'Not recorded' }}</strong><small>Identified by {{ $screeningImage?->part_detection_provider ?? 'the image-view check' }} before disease classification.</small></div>
            </div>
            <h3 class="case-result-title">{{ $prediction->display_label }}</h3>
            <p class="case-result-message">{{ $prediction->result_message }}</p>
            <div class="confidence"><div class="confidence-head"><span>Probability</span><strong>{{ round($prediction->confidence * 100) }}%</strong></div><div class="confidence-track"><div class="confidence-fill" style="width:{{ max(0, min(100, round($prediction->confidence * 100))) }}%"></div></div><p class="field-help" style="margin-top:8px">@if($threshold !== null)Configured inconclusive threshold: {{ round($threshold * 100) }}%. @endif Probability reflects the model's relative certainty, not proof that disease is present.</p></div>
            <dl class="case-result-metadata">
                <div><dt>Model</dt><dd>{{ $prediction->modelVersion?->architecture ?? 'Model information not recorded' }}</dd></div>
                <div><dt>Version</dt><dd>{{ $prediction->modelVersion?->version_name ?? 'Not recorded' }}</dd></div>
                <div><dt>Image quality</dt><dd>{{ ucwords(str_replace('_', ' ', $prediction->quality_status)) }}</dd></div>
                @if($prediction->inference_time_ms)<div><dt>Inference time</dt><dd>{{ $prediction->inference_time_ms }} ms</dd></div>@endif
            </dl>
            @if(filled($prediction->quality_flags))
                <div class="case-quality-flags"><strong>Result checks</strong><div>@foreach($prediction->quality_flags as $flag)<span>{{ ucwords(str_replace('_', ' ', $flag)) }}</span>@endforeach</div></div>
            @endif
            <p class="case-result-disclaimer">{{ $prediction->disclaimer }}</p>
        </section>
        @else
        <p class="field-help">No saved screening result is available for this case.</p>
        @endif
        <dl class="meta-grid case-meta">
            <div class="meta-item"><dt>Farm</dt><dd>{{ $case->farmProfile?->farm_name ?: 'Not assigned' }}</dd></div>
            <div class="meta-item"><dt>Farm section</dt><dd>{{ $case->farm_section ?: 'Not provided' }}</dd></div>
            <div class="meta-item"><dt>Banana tree codename</dt><dd>{{ $case->tree_codename ?: 'Not provided' }}</dd></div>
            <div class="meta-item"><dt>Variety</dt><dd>{{ $case->variety ?: 'Not provided' }}</dd></div>
            <div class="meta-item"><dt>Plant age</dt><dd>{{ $case->plant_age ? $case->plant_age.' '.$case->plant_age_unit : 'Not provided' }}</dd></div>
            <div class="meta-item"><dt>Case status</dt><dd>{{ ucwords($case->status) }}</dd></div>
        </dl>
        @if($case->symptom_notes)<div class="case-notes"><strong>Visible symptoms reported</strong><p>{{ $case->symptom_notes }}</p></div>@endif
    </article>

    <article class="card card-pad" style="margin-top:18px"><div class="section-head"><h2>Case images</h2><span class="field-help">Stored privately</span></div><div class="case-image-grid">@foreach($case->images as $image)<figure><img src="{{ route('cases.images.show',[$case,$image]) }}" alt="{{ $image->image_type === 'original' ? 'Original screening image' : 'Follow-up image' }}"><figcaption>{{ ucwords(str_replace('_',' ',$image->image_type)) }} - {{ ucwords(str_replace('_',' ',$image->specific_view ?? $image->view_type)) }}</figcaption></figure>@endforeach</div></article>

    <article class="card card-pad" style="margin-top:18px"><div class="section-head"><h2>Follow-up history</h2><span class="field-help">{{ $case->followUps->count() }} update(s)</span></div>
        @forelse($case->followUps as $followUp)<div class="history-entry"><span class="history-dot"></span><div><div class="history-head"><strong>{{ ucwords($followUp->case_status) }}</strong><time>{{ $followUp->created_at->format('M j, Y g:i A') }}</time></div><p>{{ $followUp->observation }}</p>@if($followUp->action_taken)<small><b>Action recorded:</b> {{ $followUp->action_taken }}</small>@endif<small>Added by {{ $followUp->creator->name }}</small></div></div>@empty<p class="field-help">No follow-up observations have been added.</p>@endforelse
    </article>

    @if(auth()->user()->role === 'monitoring_personnel')
    <article class="card card-pad" style="margin-top:18px"><h2>Add follow-up observation</h2><form class="form-stack" method="POST" action="{{ route('cases.follow-ups.store',$case) }}" enctype="multipart/form-data">@csrf
        <label class="field"><span class="field-label">Current observation</span><textarea required name="observation" maxlength="2000" class="input" placeholder="Describe visible changes since the previous record."></textarea></label>
        <label class="field"><span class="field-label">Action taken</span><textarea name="action_taken" maxlength="2000" class="input" placeholder="Record field actions, consultation, or monitoring steps."></textarea></label>
        <div class="field-grid">
            <label class="field"><span class="field-label">Updated case status</span><select required name="case_status" class="input">@foreach(['open','improving','unchanged','worsening','referred','closed'] as $status)<option value="{{ $status }}">{{ ucwords($status) }}</option>@endforeach</select></label>
            <label class="field"><span class="field-label">Optional follow-up image</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="input"></label>
        </div>
        <button class="btn btn-primary" type="submit">Save follow-up</button>
    </form></article>
    @endif
</div>

<aside class="case-detail-side">
    <article class="card card-pad sticky-help">
        <p class="eyebrow">Farm owner review</p>
        <h2>{{ ucwords(str_replace('_',' ',$case->review_status)) }}</h2>
        @if($case->reviewer)
            <p class="field-help">Reviewed by {{ $case->reviewer->name }} on {{ $case->reviewed_at?->format('M j, Y') }}</p>
        @endif
        @if($case->review_notes)
            <p>{{ $case->review_notes }}</p>
        @endif
        @if(auth()->user()->role === 'farm_owner')
            <form class="form-stack" method="POST" action="{{ route('cases.review',$case) }}">
                @csrf
                <label class="field"><span class="field-label">Review decision</span><select name="review_status" required class="input"><option value="reviewed">Reviewed</option><option value="needs_follow_up">Needs follow-up</option><option value="referred">Recommend professional referral</option><option value="closed">Close case</option></select></label>
                <label class="field"><span class="field-label">Owner notes</span><textarea name="review_notes" maxlength="2000" class="input"></textarea></label>
                <button class="btn btn-primary" type="submit">Save owner review</button>
            </form>
        @else
            <p class="field-help">The Farm Owner reviews submitted reports. Historical AI results remain unchanged by review decisions.</p>
        @endif
    </article>
    <article class="notice notice-amber" style="margin-top:18px"><span>Seek professional assessment or laboratory confirmation for uncertain, low-confidence, serious, or worsening cases.</span></article>
</aside>
</section>
@endsection
