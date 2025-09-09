<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    use HasApiTokens, HasFactory, Notifiable;

    protected $guard_name = 'web';

    protected $fillable = [
        'name','email','password',
        'department','is_active',  
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'bool',      
    ];

    protected static function booted(): void {
        static::created(function ($user) {
            if (\Spatie\Permission\Models\Role::whereName('Viewer')->exists()) {
                $user->assignRole('Viewer');
            }
        });
    }
}
