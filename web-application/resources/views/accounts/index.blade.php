@extends('layouts.app')
@section('title', 'Manage accounts - BananaShield')
@section('content')
<div class="hero-bar">
    <div>
        <h1 class="page-title">Manage accounts.</h1>
        <p class="page-copy">Create and review the Monitoring Personnel accounts authorized to conduct screenings and submit farm reports.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('dashboard') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m14 6-6 6 6 6"/></svg>Back to dashboard</a>
</div>

<div class="analytics-warning">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3 4 7v5c0 5 3 8 8 10 5-2 8-5 8-10V7z"/><path d="M9 12h6"/></svg>
    <div><strong>Monitoring access only.</strong> Accounts created here cannot receive Farm Owner or System Administrator permissions.</div>
</div>

<section class="card card-pad account-management-card account-module">
    <div class="section-head settings-section-head">
        <div><h2>Monitoring personnel accounts</h2><p class="field-help">New accounts are activated immediately and can sign in with their password.</p></div>
        <span class="field-help">{{ $monitoringAccounts->total() }} {{ \Illuminate\Support\Str::plural('account', $monitoringAccounts->total()) }}</span>
    </div>
    <div class="account-manager-grid">
        <form class="account-create-form form-stack" method="POST" action="{{ route('accounts.store') }}">
            @csrf
            <div><h3>Create monitoring account</h3><p class="field-help">The role is fixed to Monitoring Personnel.</p></div>
            <label class="field"><span class="field-label">Full name</span><input required class="input" name="monitor_name" value="{{ old('monitor_name') }}" maxlength="120" autocomplete="name"></label>
            <label class="field"><span class="field-label">Email address</span><input required type="email" class="input" name="monitor_email" value="{{ old('monitor_email') }}" maxlength="255" autocomplete="email"></label>
            <label class="field"><span class="field-label">Password</span><input required type="password" class="input" name="monitor_password" minlength="8" autocomplete="new-password" aria-describedby="monitor-password-help"><span class="field-help" id="monitor-password-help">Use at least 8 characters and share it securely.</span></label>
            <label class="field"><span class="field-label">Confirm password</span><input required type="password" class="input" name="monitor_password_confirmation" minlength="8" autocomplete="new-password"></label>
            <button class="btn btn-primary" type="submit">Create monitoring account</button>
        </form>
        <div class="account-list">
            <div class="account-scroll" tabindex="0" aria-label="Monitoring Personnel account list">
                @forelse($monitoringAccounts as $account)
                    <article class="account-card">
                        <span class="account-avatar" aria-hidden="true">{{ strtoupper(substr($account->name, 0, 2)) }}</span>
                        <div class="account-identity"><strong>{{ $account->name }}</strong><span>{{ $account->email }}</span><small>{{ $account->submitted_cases_count }} submitted {{ \Illuminate\Support\Str::plural('report', $account->submitted_cases_count) }}</small></div>
                        <div class="account-row-actions">
                            <span class="case-status {{ $account->status === 'active' ? 'success' : 'warning' }}">{{ ucfirst($account->status) }}</span>
                            <a class="btn btn-secondary account-edit-button" href="{{ route('accounts.show', $account) }}" aria-label="Edit account for {{ $account->name }}">Edit</a>
                        </div>
                    </article>
                @empty
                    <div class="empty-state account-empty"><h3>No monitoring accounts yet</h3><p>Create the first account for personnel who conduct screenings and submit farm reports.</p></div>
                @endforelse
            </div>
            @if($monitoringAccounts->hasPages())<div class="pagination-wrap">{{ $monitoringAccounts->links() }}</div>@endif
        </div>
    </div>
</section>
@endsection
