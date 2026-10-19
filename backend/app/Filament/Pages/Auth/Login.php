<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Filament\Http\Responses\Auth\LoginResponse;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username atau email')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['name' => 'username', 'tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->extraInputAttributes(['name' => 'password', 'tabindex' => 2]);
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getRememberFormComponent(),
                        Hidden::make('_token')
                            ->default(fn (): string => csrf_token())
                            ->extraAttributes([
                                'name' => '_token',
                                'value' => csrf_token(),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        
        // Find user by username or email
        $user = \App\Models\User::where("username", $data["username"])
            ->orWhere("email", $data["username"])
            ->first();
        
        // Panel Filament memakai guard web. Guard default aplikasi adalah api,
        // jadi kredensial dan role harus lolos sebelum sesi web dibuat.
        if (!$user || !$user->is_active || $user->role !== 'super_admin' || !Hash::check($data["password"], $user->password)) {
            $this->failureNotification("Username atau kata sandi tidak valid, atau akun tidak memiliki akses panel.");
            return null;
        }
        
        Auth::guard('web')->login($user, $data["remember"] ?? false);
        request()->session()->regenerate();
        
        return app(LoginResponse::class);
    }
}
