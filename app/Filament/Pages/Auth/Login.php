<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Filament\Http\Responses\Auth\Contracts\LoginResponse; 

class Login extends BaseLogin
{
    // Return type harus sama persis dengan base class
    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        $user = User::where('email', $data['email'] ?? '')->first();

        // Kredensial salah → pakai error standar Filament
        if (! $user || ! Hash::check($data['password'] ?? '', $user->password)) {
            $this->throwFailureValidationException();
        }

        // User non-aktif → tampilkan pesan di halaman login
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Akun sudah di non-aktifkan silahkan hubungi admin untuk pengaktifan akun',
            ]);
        }

        // Lanjutkan login default (remember me, redirect, dll.)
        return parent::authenticate();
    }
}
