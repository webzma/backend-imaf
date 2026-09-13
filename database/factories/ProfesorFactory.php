<?php

namespace Database\Factories;

use App\Models\Profesor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Profesor>
 */
class ProfesorFactory extends Factory
{
    protected $model = Profesor::class;

    public function definition(): array
    {
        // Ensure catalog tables have at least one record for FK constraints
        $this->ensureCatalogsExist();

        return [
            'user_id' => User::factory()->state(['role' => 'profesor']),
            'cedula' => fake()->unique()->numerify('########'),
            'telefono' => fake()->numerify('809#######'),
            'especialidad_id' => DB::table('especialidades')->inRandomOrder()->first()?->id,
            'departamento_id' => DB::table('departamentos')->inRandomOrder()->first()?->id,
            'titulo_id' => DB::table('titulos')->inRandomOrder()->first()?->id,
            'genero' => 'masculino',
        ];
    }

    private function ensureCatalogsExist(): void
    {
        if (! DB::table('especialidades')->count()) {
            DB::table('especialidades')->insert(['nombre' => 'General', 'created_at' => now(), 'updated_at' => now()]);
        }
        if (! DB::table('departamentos')->count()) {
            DB::table('departamentos')->insert(['nombre' => 'General', 'created_at' => now(), 'updated_at' => now()]);
        }
        if (! DB::table('titulos')->count()) {
            DB::table('titulos')->insert(['nombre' => 'Licenciatura', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
