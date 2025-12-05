<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Mutator para validar role antes de salvar
     *
     * @param string $value
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setRoleAttribute($value): void
    {
        $validRoles = ['admin', 'user'];

        if (!in_array($value, $validRoles, true)) {
            throw new \InvalidArgumentException(
                "Role inválido: '$value'. Valores aceitos: " . implode(', ', $validRoles)
            );
        }

        $this->attributes['role'] = $value;
    }
}
