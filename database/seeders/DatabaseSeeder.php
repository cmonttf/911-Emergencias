<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // TipoVehiculoSeeder va primero: tanto TipoEdificioSeeder (el
            // vehículo inicial gratis) como TipoMisionSeeder (los requisitos)
            // necesitan que los tipos de vehículo ya existan.
            TipoVehiculoSeeder::class,
            TipoEdificioSeeder::class,
            TipoMisionSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
