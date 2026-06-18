<?php

namespace Database\Factories;

use App\Models\Profesor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profesor>
 */
class ProfesorFactory extends Factory
{
    protected $model = Profesor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'profesor']),
            'cedula' => fake()->unique()->numerify('001-#######-#'),
            'telefono' => fake()->numerify('809#######'),
            'especialidad' => fake()->word(),
            'genero' => 'masculino',
        ];
    }
}
