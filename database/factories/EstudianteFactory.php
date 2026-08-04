<?php

namespace Database\Factories;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estudiante>
 */
class EstudianteFactory extends Factory
{
    protected $model = Estudiante::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'estudiante']),
            'nombre' => fake()->name(),
            'cedula' => fake()->unique()->numerify('########'),
            'telefono' => fake()->numerify('809#######'),
            'genero' => 'femenino',
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'activo',
        ];
    }
}
