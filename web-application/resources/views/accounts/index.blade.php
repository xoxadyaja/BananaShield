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

<section class="account-module">
    <header class="account-module-head">
        <div><h2>Monitoring personnel accounts</h2><p>Review authorized screening personnel, their status, and submitted reports.</p></div>
        <span>{{ $monitoringAccounts->total() }} {{ \Illuminate\Support\Str::plural('account', $monitoringAccounts->total()) }}</span>
    </header>
    <div class="account-manager-grid">
        <span class="sr-only">Create monitoring account</span>
        <section class="account-list card" aria-labelledby="account-directory-title">
            <header class="account-list-head">
                <div><h3 id="account-directory-title">Authorized personnel</h3><p>Open a profile to update access or review activity.</p></div>
            </header>
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
        </section>
        <aside class="account-create-panel" aria-labelledby="create-monitor-title">
            <form class="account-create-form form-stack" method="POST" action="{{ route('accounts.store') }}">
                @csrf
                <div class="account-create-head"><span class="account-create-icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-4 2-7 6-7s6 3 6 7M18 8v6M15 11h6"/></svg></span><div><h3 id="create-monitor-title">Create monitoring account</h3><p class="field-help">Accounts are activated immediately.</p></div></div>
                <label class="field"><span class="field-label">Full name</span><input required class="input" name="monitor_name" value="{{ old('monitor_name') }}" maxlength="120" autocomplete="name"></label>
                <label class="field"><span class="field-label">Email address</span><input required type="email" class="input" name="monitor_email" value="{{ old('monitor_email') }}" maxlength="255" autocomplete="email"></label>
                <div class="account-password-fields"><label class="field"><span class="field-label">Password</span><input required type="password" class="input" name="monitor_password" minlength="8" autocomplete="new-password" aria-describedby="monitor-password-help"></label><label class="field"><span class="field-label">Confirm password</span><input required type="password" class="input" name="monitor_password_confirmation" minlength="8" autocomplete="new-password"></label></div>
                <p class="field-help" id="monitor-password-help">Use at least 8 characters and share the temporary password securely.</p>
                <button class="btn btn-primary" type="submit">Create account <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
            </form>
        </aside>
    </div>
</section>
@endsection
