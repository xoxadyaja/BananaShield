<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }

    public function login(Request $request, AuditLogger $audit)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $credentials['email'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'The email address or password is incorrect.'])->onlyInput('email');
        }
        if ($user->status !== 'active') return back()->withErrors(['email' => 'This account is inactive. Please contact BananaShield support.'])->onlyInput('email');
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $audit->record('auth.login', $user);
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, AuditLogger $audit)
    {
        $audit->record('auth.logout', $request->user());
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'You have been signed out safely.');
    }
}
