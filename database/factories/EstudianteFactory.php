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

    /** Lo inscribe con el pago aprobado y deja ese curso como el actual. */
    public function enCurso(int $cursoId, string $aprobacion = 'pendiente'): static
    {
        return $this->afterCreating(function (Estudiante $estudiante) use ($cursoId, $aprobacion) {
            $estudiante->asignarCursoActual($cursoId)->update(['estado_aprobacion_curso' => $aprobacion]);
            $estudiante->unsetRelation('inscripcionActual');
        });
    }
}
