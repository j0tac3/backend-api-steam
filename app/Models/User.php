<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array', // 🚀 AÑADIDO: cast a array
        ];
    }

    protected $fillable = [
        'name', 'email', 'password', 'username', 'is_public', // 👈 Añade estos dos
    ];

    public function games()
    {
        return $this->hasMany(\App\Models\Game::class);
    }

    // 🚀 Generador automático de usernames (para que no haya nulos)
    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->username) {
                // Convierte "juan.perez@gmail.com" en "juanperez"
                $base = strstr($user->email, '@', true);
                $user->username = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $base));
            }
        });
    }
}
