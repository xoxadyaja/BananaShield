@extends('layouts.app')
@section('title', 'Sign in - BananaShield')
@section('content')
<main class="auth-page" id="main-content" tabindex="-1">
    <section class="auth-panel">
        <div class="auth-form">
            <div class="auth-identity"><img src="{{ asset('images/bananashield-logo.svg') }}?v={{ filemtime(public_path('images/bananashield-logo.svg')) }}" alt="" aria-hidden="true"><span>Authorized farm workspace</span></div>
            <p class="eyebrow">Welcome back</p>
            <h1 class="auth-title">Sign in to BananaShield</h1>
            <p class="auth-copy">Access screening, farm reports, monitoring, advisory information, or system administration according to your assigned role.</p>
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
            <form class="form-stack" method="POST" action="{{ route('login.attempt') }}">@csrf
                <label class="field"><span class="field-label">Email address</span><span class="input-wrap"><svg class="input-icon icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><input class="input" required type="email" name="email" value="{{ old('email') }}" placeholder="name@bananashield.local" autocomplete="email" autofocus></span></label>
                <label class="field"><span class="field-label">Password</span><span class="input-wrap password-wrap"><svg class="input-icon icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><input id="login-password" class="input" required type="password" name="password" placeholder="Enter your password" autocomplete="current-password"><button class="password-toggle" type="button" aria-label="Show password" aria-controls="login-password" aria-pressed="false"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></span></label>
                <label class="checkbox"><input type="checkbox" name="remember"><span>Keep me signed in on this trusted device</span></label>
                <button class="btn btn-primary btn-block" type="submit">Sign in securely <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14M14 7l5 5-5 5"/></svg></button>
            </form>
            <p class="auth-footer">Accounts are issued and managed by the BananaShield System Administrator.</p>
        </div>
    </section>
    <aside class="auth-aside"><div class="auth-story"><span class="pill"><img src="{{ asset('images/bananashield-logo.svg') }}?v={{ filemtime(public_path('images/bananashield-logo.svg')) }}" alt="" aria-hidden="true"> Built for farm-level decision support</span><h2>Screen visibly.<br>Report clearly.<br>Monitor responsibly.</h2><p>One centralized workspace for preliminary image classification, farm reporting, follow-ups, advisory support, and operational analytics.</p><div class="trust-row"><div class="trust-item"><b>1 model</b><span>Both guided capture paths</span></div><div class="trust-item"><b>4 classes</b><span>Supported plant conditions</span></div><div class="trust-item"><b>Private</b><span>Protected case images</span></div></div></div></aside>
</main>
@endsection
@push('scripts')<script>const toggle=document.querySelector('.password-toggle'),password=document.getElementById('login-password');toggle?.addEventListener('click',()=>{const showing=password.type==='text';password.type=showing?'password':'text';toggle.setAttribute('aria-label',showing?'Show password':'Hide password');toggle.setAttribute('aria-pressed',String(!showing));toggle.classList.toggle('active',!showing);});</script>@endpush
