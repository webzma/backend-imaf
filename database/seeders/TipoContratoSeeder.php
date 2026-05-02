<?php

namespace Database\Seeders;

use App\Models\TipoContrato;
use Illuminate\Database\Seeder;

class TipoContratoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TipoContrato::updateOrCreate(['nombre' => 'Instructor IMAF']);
        TipoContrato::updateOrCreate(['nombre' => 'Instructor Externo']);
    }
}
