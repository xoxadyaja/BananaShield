<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MonitoringAccountController extends Controller
{
    public function index()
    {
        $monitoringAccounts = User::query()
            ->where('role', 'monitoring_personnel')
            ->withCount(['submittedCases' => fn ($query) => $query->reportable()])
            ->orderBy('name')
            ->paginate(12);

        return view('accounts.index', compact('monitoringAccounts'));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'monitor_name' => 'required|string|max:120',
            'monitor_email' => 'required|email|max:255|unique:users,email',
            'monitor_password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'monitor_password.confirmed' => 'The password confirmation does not match.',
        ]);

        $user = User::create([
            'name' => $data['monitor_name'],
            'email' => $data['monitor_email'],
            'password' => $data['monitor_password'],
            'role' => 'monitoring_personnel',
            'status' => 'active',
        ]);

        $audit->record('farm.monitoring_account_created', $user, metadata: ['role' => $user->role]);

        return back()->with('success', 'Monitoring Personnel account created and ready to sign in.');
    }

    public function show(User $account)
    {
        $this->ensureMonitoringAccount($account);
        $account->loadCount([
            'submittedCases' => fn ($query) => $query->reportable(),
            'followUps',
        ]);
        $latestCase = $account->submittedCases()->reportable()->latest('observed_at')->first();

        return view('accounts.show', compact('account', 'latestCase'));
    }

    public function update(Request $request, User $account, AuditLogger $audit)
    {
        $this->ensureMonitoringAccount($account);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account->id)],
            'status' => 'required|in:active,inactive',
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
        ];
        if (! empty($data['password'])) {
            $updates['password'] = $data['password'];
        }

        $account->update($updates);
        $audit->record('farm.monitoring_account_updated', $account, metadata: [
            'status' => $account->status,
            'password_changed' => isset($updates['password']),
        ]);

        return back()->with('success', 'Monitoring Personnel account updated.');
    }

    public function destroy(User $account, AuditLogger $audit)
    {
        $this->ensureMonitoringAccount($account);

        if ($account->submittedCases()->exists() || $account->followUps()->exists()) {
            return back()->withErrors([
                'account' => 'This account has farm records and cannot be deleted. Set its status to Inactive instead.',
            ]);
        }

        $accountId = $account->id;
        $accountName = $account->name;
        DB::transaction(function () use ($account, $accountId, $accountName, $audit) {
            $account->delete();
            $audit->record('farm.monitoring_account_deleted', User::class, $accountId, [
                'name' => $accountName,
                'role' => 'monitoring_personnel',
            ]);
        });

        return redirect()->route('accounts.index')->with('success', 'Monitoring Personnel account deleted.');
    }

    private function ensureMonitoringAccount(User $account): void
    {
        abort_unless($account->role === 'monitoring_personnel', 404);
    }
}
