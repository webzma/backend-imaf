<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        // Solo inserta si la tabla está vacía
        if (! DB::table('especialidades')->count()) {
            $especialidades = [
                'Matemáticas', 'Física', 'Química', 'Biología',
                'Ciencias Naturales', 'Lengua y Literatura', 'Historia',
                'Geografía', 'Inglés', 'Arte y Cultura', 'Música',
                'Educación Física', 'Computación e Informática',
                'Administración', 'Economía',
            ];
            foreach ($especialidades as $nombre) {
                DB::table('especialidades')->insert([
                    'nombre' => $nombre,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! DB::table('departamentos')->count()) {
            $departamentos = [
                'Ciencias Exactas', 'Ciencias Naturales', 'Ciencias Sociales',
                'Humanidades', 'Idiomas', 'Arte y Cultura', 'Tecnología',
                'Educación Física', 'Administración y Economía', 'Música',
            ];
            foreach ($departamentos as $nombre) {
                DB::table('departamentos')->insert([
                    'nombre' => $nombre,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! DB::table('titulos')->count()) {
            DB::table('titulos')->insert([
                ['nombre' => 'Licenciatura', 'created_at' => now(), 'updated_at' => now()],
                ['nombre' => 'Maestría', 'created_at' => now(), 'updated_at' => now()],
                ['nombre' => 'Doctorado', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
}
