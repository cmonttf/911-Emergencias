<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Dominio\Economia\Models\Transaccion;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Models\Mision;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'comuna_inicial'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
     * Libro de transacciones de créditos y moneda premium del jugador.
     */
    public function transacciones(): HasMany
    {
        return $this->hasMany(Transaccion::class, 'jugador_id');
    }

    /**
     * Edificios construidos por el jugador.
     */
    public function edificios(): HasMany
    {
        return $this->hasMany(Edificio::class, 'jugador_id');
    }

    /**
     * Misiones generadas para el jugador.
     */
    public function misiones(): HasMany
    {
        return $this->hasMany(Mision::class, 'jugador_id');
    }
}
