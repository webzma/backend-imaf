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
        TipoContrato::updateOrCreate(['nombre' => 'Contrato IMAF']);
        TipoContrato::updateOrCreate(['nombre' => 'Contrato Externo']);
    }
}
