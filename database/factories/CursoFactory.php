<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Profesor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    protected $model = Curso::class;

    public function definition(): array
    {
        return [
            'profesor_id' => Profesor::factory(),
            'nombre' => fake()->unique()->sentence(3),
            'limite_cupo' => 30,
            'precio' => fake()->randomFloat(2, 0, 5000),
            'descripcion' => fake()->paragraph(),
            'estado' => 'activo',
        ];
    }
}
