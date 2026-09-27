@extends('layouts.app')
@section('title', 'New AI screening - BananaShield')
@section('content')
<div class="hero-bar"><div><h1 class="page-title">Upload a clear banana plant image.</h1><p class="page-copy">BananaShield automatically analyzes the visible banana area before generating a preliminary screening result.</p></div></div>

<div class="stepper" aria-label="Screening progress">
    <div class="step current" data-step="1"><span class="step-num">1</span><span>Add image</span></div><span class="step-line" data-line="1"></span>
    <div class="step" data-step="2"><span class="step-num">2</span><span>Plant context</span></div><span class="step-line" data-line="2"></span>
    <div class="step" data-step="3"><span class="step-num">3</span><span>Result</span></div>
</div>

<form id="screening-form" method="POST" action="{{ route('screenings.store') }}" enctype="multipart/form-data">@csrf
<input type="hidden" name="part_detection_receipt" id="part-detection-receipt" value="">
<div class="screening-layout"><div>
    <section class="card form-section wizard-panel current" data-panel="1">
        <div class="form-section-title"><div><h2>Add a clear photograph</h2><span class="field-help">JPEG, PNG, or WEBP &middot; up to 5 MB</span></div></div>
        <div class="field-grid capture-layout">
            <div class="upload-box" id="upload-box"><input required accept="image/jpeg,image/png,image/webp" type="file" name="image" id="image-input" class="upload-input"><div id="upload-prompt"><span class="upload-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 7h4l2-2h4l2 2h4v12H4z"/><circle cx="12" cy="13" r="3"/></svg></span><b>Add a banana plant photo</b><small>Use natural light, keep the main plant area in focus, and avoid blur or obstruction.</small><div class="upload-actions"><button class="btn btn-primary" type="button" data-image-picker="camera">Capture photo</button><button class="btn btn-secondary" type="button" data-image-picker="upload">Choose from device</button></div></div><div class="preview-shell" id="preview-shell" hidden><img id="image-preview" src="{{ asset('images/banana-field.svg') }}" alt="Selected banana plant image preview"><button class="btn btn-secondary preview-replace" type="button" data-image-picker="upload">Choose another image</button></div></div>
            <aside class="card capture-guidelines" aria-labelledby="capture-guidelines-title"><div class="help-top"><span class="feature-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 18h6M10 22h4M8 14c-1-1-2-3-2-5a6 6 0 1 1 12 0c0 2-1 4-2 5l-1 2H9z"/></svg></span><h3 id="capture-guidelines-title">Image Capturing Guidelines</h3><p>Clear photos help the system produce a useful preliminary result.</p></div><ul class="capture-guide-list"><li><span class="capture-guide-check" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 12 4 4 8-9"/></svg></span><span>Use sufficient natural light</span></li><li><span class="capture-guide-check" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 12 4 4 8-9"/></svg></span><span>Keep the banana plant area centered</span></li><li><span class="capture-guide-check" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 12 4 4 8-9"/></svg></span><span>Avoid blur, obstruction, and digital zoom</span></li><li><span class="capture-guide-check" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 12 4 4 8-9"/></svg></span><span>Retake images that do not clearly show the plant</span></li></ul></aside>
        </div>

        <section class="part-detection" id="part-detection" role="status" aria-live="polite" aria-atomic="true" tabindex="-1" hidden>
            <span class="part-detection-mark" aria-hidden="true"><svg class="icon detection-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20c8 0 15-5 16-16-8 1-15 8-16 16Z"/></svg><span class="detection-spinner"></span></span>
            <div class="part-detection-copy"><span class="part-detection-label" id="part-detection-label">Banana plant validation</span><h3 id="part-detection-title">Analyzing image...</h3><p id="part-detection-message">Gemini is checking whether the image shows a banana plant and identifying the primary plant view.</p><strong id="detected-part" hidden></strong><div class="part-detection-actions" id="part-detection-actions" hidden><button class="btn btn-secondary" type="button" data-image-picker="camera">Capture another image</button><button class="btn btn-secondary" type="button" data-image-picker="upload">Choose another image</button></div></div>
        </section>

        <div class="wizard-actions wizard-actions-end"><button class="btn btn-primary" id="continue-to-context" type="button" data-next="2" disabled>Continue to context</button></div>
    </section>

    <section class="card form-section wizard-panel" data-panel="2" hidden>
        <div class="form-section-title"><div><h2>Add available plant context</h2><span class="field-help">Stored with the farm case; not used as model input</span></div></div>
        <div class="field-grid">
            <label class="field"><span class="field-label">Banana variety</span><select name="variety" class="input"><option value="">Not provided</option>@foreach(['Cardava','Binangay','Tundan','Other','Unknown'] as $variety)<option value="{{ $variety }}" @selected(old('variety')===$variety)>{{ $variety }}</option>@endforeach</select></label>
            <label class="field"><span class="field-label">Observation date</span><input required type="date" name="observed_at" value="{{ old('observed_at', date('Y-m-d')) }}" class="input"></label>
            <label class="field"><span class="field-label">Approximate plant age</span><input type="number" min="1" max="3650" name="plant_age" value="{{ old('plant_age') }}" class="input" placeholder="Example: 8"></label>
            <label class="field"><span class="field-label">Age unit</span><select name="plant_age_unit" class="input"><option value="">Not provided</option><option value="weeks" @selected(old('plant_age_unit')==='weeks')>Weeks</option><option value="months" @selected(old('plant_age_unit')==='months')>Months</option><option value="years" @selected(old('plant_age_unit')==='years')>Years</option></select></label>
            <label class="field"><span class="field-label">Farm section</span><input name="farm_section" value="{{ old('farm_section') }}" maxlength="120" class="input" list="farm-section-options" placeholder="Example: North Block A"><datalist id="farm-section-options">@foreach($farmSections as $section)<option value="{{ $section }}">@endforeach</datalist><span class="field-help" style="display:block;margin-top:8px">Choose a configured farm section or enter another operational label.</span></label>
            <label class="field"><span class="field-label">Banana tree codename</span><input name="tree_codename" value="{{ old('tree_codename') }}" maxlength="120" class="input" placeholder="Example: BA-T014" autocomplete="off"><span class="field-help" style="display:block;margin-top:8px">Add the tag or unique field name used to identify this specific banana tree.</span></label>
            <label class="field"><span class="field-label">Visible symptoms</span><textarea name="symptom_notes" maxlength="2000" class="input" placeholder="Describe spots, yellowing, wilting, drying, stunted growth, or abnormal leaf arrangement.">{{ old('symptom_notes') }}</textarea></label>
        </div>
        <div class="wizard-actions"><button class="btn btn-secondary" type="button" data-back="1">Back</button><button class="btn btn-primary" type="submit">Run disease screening</button></div>
    </section>
</div>

</div>
</form>

<div class="processing-overlay" id="processing-overlay" aria-hidden="true" role="status" aria-live="polite"><div class="processing-card"><div class="processing-mark"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20c8 0 15-5 16-16-8 1-15 8-16 16Z"/></svg><span class="processing-ring"></span></div><h2>Generating the preliminary result</h2><p class="processing-copy">The Gemini-detected plant view has been accepted. BananaShield is now sending the same image to the separate EfficientNet-B0 disease classifier.</p><div class="processing-progress"><span></span></div><ol class="processing-steps"><li class="active">Confirming the detected plant view</li><li>Preparing the accepted image</li><li>Running EfficientNet-B0 disease classification</li><li>Saving the farm case and advisory</li></ol><p class="processing-note">Keep this page open. Failed requests do not create a completed prediction record.</p></div></div>
@endsection
@push('scripts')<script>
const input=document.getElementById('image-input');
const preview=document.getElementById('image-preview');
const previewShell=document.getElementById('preview-shell');
const prompt=document.getElementById('upload-prompt');
const form=document.getElementById('screening-form');
const overlay=document.getElementById('processing-overlay');
const detection=document.getElementById('part-detection');
const detectionTitle=document.getElementById('part-detection-title');
const detectionMessage=document.getElementById('part-detection-message');
const detectionLabel=document.getElementById('part-detection-label');
const detectedPart=document.getElementById('detected-part');
const detectionActions=document.getElementById('part-detection-actions');
const detectionReceipt=document.getElementById('part-detection-receipt');
const continueButton=document.getElementById('continue-to-context');
const detectionUrl=@json(route('screenings.detect-part'));
const panels=[...document.querySelectorAll('.wizard-panel')];
const steps=[...document.querySelectorAll('.step')];
const lines=[...document.querySelectorAll('.step-line')];
let detectionRequest;
let previewUrl;

function setDetectionState(state,{title,message,part='',label='Banana plant validation',receipt=''}={}){
    detection.hidden=false;
    detection.dataset.state=state;
    detectionTitle.textContent=title;
    detectionMessage.textContent=message;
    detectionLabel.textContent=label;
    detectedPart.textContent=part;
    detectedPart.hidden=!part;
    detectionActions.hidden=state==='analyzing';
    detectionReceipt.value=receipt;
    continueButton.disabled=state!=='valid';
}

function openImagePicker(source){
    if(source==='camera') input.setAttribute('capture','environment');
    else input.removeAttribute('capture');
    input.click();
}

async function analyzeImage(file){
    detectionRequest?.abort();
    detectionRequest=new AbortController();
    setDetectionState('analyzing',{
        title:'Analyzing image...',
        message:'Gemini is checking whether this is a clear banana plant image and identifying the primary plant view.'
    });

    if(!['image/jpeg','image/png','image/webp'].includes(file.type)){
        setDetectionState('error',{title:'Unsupported image format',message:'Choose a JPG, PNG, or WebP image.'});
        return;
    }
    if(file.size>5*1024*1024){
        setDetectionState('error',{title:'Image is too large',message:'Choose an image that is 5 MB or smaller.'});
        return;
    }

    const body=new FormData();
    body.append('image',file);
    try{
        const response=await fetch(detectionUrl,{
            method:'POST',
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},
            body,
            signal:detectionRequest.signal
        });
        const data=await response.json();
        if(!response.ok){
            const validationMessage=data.errors?.image?.[0]||data.message||'Image analysis is temporarily unavailable. Please try again.';
            setDetectionState('error',{title:'Image cannot be analyzed',message:validationMessage});
            return;
        }
        if(data.is_banana_image===false){
            setDetectionState('invalid',{
                title:'Invalid banana image',
                message:'The submitted image does not appear to contain a banana plant. Please capture or upload a clear image of the banana plant.'
            });
            return;
        }
        if(data.usable!==true||data.part==='Unknown'){
            setDetectionState('unclear',{
                title:'Image cannot be analyzed',
                message:'We detected a possible banana plant, but the image is not clear enough. Please capture another image with proper lighting, focus, and visibility.'
            });
            return;
        }
        if(!data.detection_receipt){
            setDetectionState('error',{
                title:'Image analysis is incomplete',
                message:'The image was recognized, but its secure analysis receipt is missing. Please try again.'
            });
            return;
        }
        setDetectionState('valid',{
            title:'Detected image view',
            message:data.message,
            part:data.part,
            label:'Ready for disease screening',
            receipt:data.detection_receipt
        });
    }catch(error){
        if(error.name==='AbortError') return;
        setDetectionState('error',{
            title:'Image analysis is unavailable',
            message:'We could not reach the image-analysis service. Check your connection and try again.'
        });
    }
}

function validateStep(number){
    if(number===1&&!detectionReceipt.value){
        detection.focus();
        return false;
    }
    for(const field of document.querySelector(`[data-panel="${number}"]`).querySelectorAll('input,select,textarea')){
        if(!field.checkValidity()){
            field.reportValidity();
            return false;
        }
    }
    return true;
}

function showStep(number){
    panels.forEach(panel=>{
        const active=Number(panel.dataset.panel)===number;
        panel.hidden=!active;
        panel.classList.toggle('current',active);
    });
    steps.forEach(step=>{
        const value=Number(step.dataset.step);
        step.classList.toggle('current',value===number);
        step.classList.toggle('completed',value<number);
    });
    lines.forEach(line=>line.classList.toggle('completed',Number(line.dataset.line)<number));
    window.scrollTo({top:document.querySelector('.stepper').offsetTop-95,behavior:'smooth'});
}

document.querySelectorAll('[data-image-picker]').forEach(button=>button.addEventListener('click',()=>openImagePicker(button.dataset.imagePicker)));
document.querySelectorAll('[data-next]').forEach(button=>button.addEventListener('click',()=>{
    const current=Number(button.closest('[data-panel]').dataset.panel);
    if(validateStep(current)) showStep(Number(button.dataset.next));
}));
document.querySelectorAll('[data-back]').forEach(button=>button.addEventListener('click',()=>showStep(Number(button.dataset.back))));

input?.addEventListener('change',()=>{
    const file=input.files?.[0];
    if(!file) return;
    if(previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl=URL.createObjectURL(file);
    preview.src=previewUrl;
    previewShell.hidden=false;
    prompt.hidden=true;
    detectionReceipt.value='';
    continueButton.disabled=true;
    analyzeImage(file);
});

form?.addEventListener('submit',event=>{
    if(!detectionReceipt.value){
        event.preventDefault();
        showStep(1);
        detection.focus();
        return;
    }
    if(!form.checkValidity()) return;
    overlay.classList.add('visible');
    overlay.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';
});
</script>@endpush
