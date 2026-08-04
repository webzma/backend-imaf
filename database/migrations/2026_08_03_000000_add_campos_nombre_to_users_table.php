<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Agrega las 4 columnas de nombre por separado (sin borrar `name`)
     * y rellena (backfill) los registros existentes repartiendo el
     * nombre completo según la cantidad de palabras.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('primer_nombre', 100)->nullable()->after('name');
            $table->string('segundo_nombre', 100)->nullable()->after('primer_nombre');
            $table->string('primer_apellido', 100)->nullable()->after('segundo_nombre');
            $table->string('segundo_apellido', 100)->nullable()->after('primer_apellido');
        });

        DB::table('users')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    [$primerNombre, $segundoNombre, $primerApellido, $segundoApellido] = $this->repartirNombre($user->name);

                    DB::table('users')->where('id', $user->id)->update([
                        'primer_nombre' => $primerNombre,
                        'segundo_nombre' => $segundoNombre,
                        'primer_apellido' => $primerApellido,
                        'segundo_apellido' => $segundoApellido,
                    ]);
                }
            });
    }

    /**
     * Heurística por cantidad de palabras:
     *  1 palabra  -> primer_nombre
     *  2 palabras -> primer_nombre + primer_apellido
     *  3 palabras -> primer_nombre + segundo_nombre + primer_apellido
     *  4+ palabras -> primer_nombre + (palabras del medio) + primer_apellido + segundo_apellido
     */
    private function repartirNombre(?string $nombre): array
    {
        if (! $nombre || trim($nombre) === '') {
            return [null, null, null, null];
        }

        $partes = preg_split('/\s+/u', trim($nombre));
        $total = count($partes);

        return match (true) {
            $total === 1 => [$partes[0], null, null, null],
            $total === 2 => [$partes[0], null, $partes[1], null],
            $total === 3 => [$partes[0], $partes[1], $partes[2], null],
            default => [
                $partes[0],
                implode(' ', array_slice($partes, 1, $total - 3)),
                $partes[$total - 2],
                $partes[$total - 1],
            ],
        };
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido']);
        });
    }
};
