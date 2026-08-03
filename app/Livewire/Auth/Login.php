<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public string $error = '';

    public function login(): void
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::guard('godadmin')->attempt(['email' => $this->email, 'password' => $this->password])) {
            $this->error = 'Invalid credentials.';

            return;
        }

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirect('/god/dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts.god');
    }
}
