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
        TipoContrato::query()->updateOrCreate(['nombre' => 'Contrato IMAF']);
        TipoContrato::query()->updateOrCreate(['nombre' => 'Contrato Externo']);
    }
}
