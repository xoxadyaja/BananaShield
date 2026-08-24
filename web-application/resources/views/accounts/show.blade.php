@extends('layouts.app')
@section('title', 'Monitoring account details - BananaShield')
@section('content')
<div class="hero-bar">
    <div>
        <h1 class="page-title">Monitoring account details.</h1>
        <p class="page-copy">Review account activity and update the access details for {{ $account->name }}.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('accounts.index') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m14 6-6 6 6 6"/></svg>Back to accounts</a>
</div>

<section class="account-detail-grid">
    <div class="account-edit-column">
        <form class="card card-pad form-stack account-edit-form" method="POST" action="{{ route('accounts.update', $account) }}">
            @csrf @method('PATCH')
            <div class="settings-card-head">
                <span class="settings-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20c0-5 3-8 8-8s8 3 8 8"/><circle cx="12" cy="6" r="3"/></svg></span>
                <div><h2>Edit account</h2><p>The role remains fixed to Monitoring Personnel.</p></div>
            </div>
            <label class="field"><span class="field-label">Full name</span><input required class="input" name="name" value="{{ old('name', $account->name) }}" maxlength="120" autocomplete="name"></label>
            <label class="field"><span class="field-label">Email address</span><input required type="email" class="input" name="email" value="{{ old('email', $account->email) }}" maxlength="255" autocomplete="email"></label>
            <label class="field"><span class="field-label">Account status</span><select required class="input" name="status"><option value="active" @selected(old('status', $account->status) === 'active')>Active</option><option value="inactive" @selected(old('status', $account->status) === 'inactive')>Inactive</option></select><span class="field-help account-edit-help">Inactive accounts cannot sign in, but their existing farm cases remain available.</span></label>

            <div class="settings-divider"></div>
            <div><h3 class="account-form-subtitle">Change password</h3><p class="field-help">Leave both fields blank to keep the current password.</p></div>
            <label class="field"><span class="field-label">New password <small>Optional</small></span><input type="password" class="input" name="password" minlength="8" autocomplete="new-password"></label>
            <label class="field"><span class="field-label">Confirm new password</span><input type="password" class="input" name="password_confirmation" minlength="8" autocomplete="new-password"></label>
            <button class="btn btn-primary" type="submit">Save account changes</button>
        </form>

        <section class="card card-pad account-danger-zone" aria-labelledby="delete-account-heading">
            <div>
                <h2 id="delete-account-heading">Delete account</h2>
                @if($account->submitted_cases_count === 0 && $account->follow_ups_count === 0)
                    <p>Permanently remove this unused account. This action cannot be undone.</p>
                @else
                    <p id="delete-account-restriction">This account has farm records and cannot be deleted. Set its status to Inactive instead.</p>
                @endif
            </div>
            @if($account->submitted_cases_count === 0 && $account->follow_ups_count === 0)
                <form method="POST" action="{{ route('accounts.destroy', $account) }}" onsubmit="return confirm('Delete this monitoring account? This action cannot be undone.')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" type="submit">Delete account</button>
                </form>
            @else
                <button class="btn btn-danger" type="button" disabled aria-describedby="delete-account-restriction">Delete account</button>
            @endif
        </section>
    </div>

    <aside class="card card-pad account-profile">
        <div class="account-profile-head"><span class="account-profile-avatar" aria-hidden="true">{{ strtoupper(substr($account->name, 0, 2)) }}</span><div><h2>{{ $account->name }}</h2><span class="case-status {{ $account->status === 'active' ? 'success' : 'warning' }}">{{ ucfirst($account->status) }}</span></div></div>
        <dl class="settings-summary-list">
            <div><dt>Role</dt><dd>Monitoring Personnel</dd></div>
            <div><dt>Email</dt><dd class="account-email">{{ $account->email }}</dd></div>
            <div><dt>Submitted reports</dt><dd>{{ number_format($account->submitted_cases_count) }}</dd></div>
            <div><dt>Latest observation</dt><dd>{{ $latestCase?->observed_at?->format('M j, Y') ?? 'No reports submitted' }}</dd></div>
            <div><dt>Account created</dt><dd>{{ $account->created_at?->format('M j, Y') ?? 'Not available' }}</dd></div>
        </dl>
        <div class="notice notice-green"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg><span>Editing this account does not change its historical screening results or submitted farm cases.</span></div>
    </aside>
</section>
@endsection
