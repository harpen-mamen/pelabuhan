<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Pages\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

// Redirect root to admin dashboard
Route::get('/', function () {
    return redirect()->route('filament.admin.pages.dashboard');
});

// Native form fallback in case Livewire JavaScript is unavailable in the browser.
Route::post('/admin/login', function (Request $request) {
    $credentials = $request->validate([
        'username' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    $user = User::query()
        ->where('username', $credentials['username'])
        ->orWhere('email', $credentials['username'])
        ->first();

    if (! $user || ! $user->is_active || $user->role !== 'super_admin' || ! Hash::check($credentials['password'], $user->password)) {
        return back()
            ->withErrors(['data.username' => 'Username/email atau kata sandi tidak valid, atau akun tidak memiliki akses panel.'])
            ->withInput($request->only('username'));
    }

    Auth::guard('web')->login($user, $request->boolean('remember'));
    $request->session()->regenerate();

    return redirect()->intended('/admin');
})->middleware('throttle:5,1')->name('filament.admin.login.fallback');

// Filament routes are automatically registered
