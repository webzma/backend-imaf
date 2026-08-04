<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNombreTest extends TestCase
{
    use RefreshDatabase;

    public function test_repartir_nombre_por_cantidad_de_palabras(): void
    {
        $this->assertSame(['Juan', null, null, null], User::repartirNombre('Juan'));
        $this->assertSame(['Juan', null, 'Pérez', null], User::repartirNombre('Juan Pérez'));
        $this->assertSame(['Juan', 'Pablo', 'Pérez', null], User::repartirNombre('Juan Pablo Pérez'));
        $this->assertSame(['Juan', 'Pablo', 'Pérez', 'Gómez'], User::repartirNombre('Juan Pablo Pérez Gómez'));
        $this->assertSame(['María', 'de los Ángeles', 'Pérez', 'Gómez'], User::repartirNombre('María de los Ángeles Pérez Gómez'));
        $this->assertSame([null, null, null, null], User::repartirNombre(null));
        $this->assertSame([null, null, null, null], User::repartirNombre('   '));
    }

    public function test_crear_con_campos_individuales_compone_name(): void
    {
        $user = User::create([
            'primer_nombre' => 'Juan',
            'segundo_nombre' => 'Pablo',
            'primer_apellido' => 'Pérez',
            'segundo_apellido' => 'Gómez',
            'email' => 'juan@example.com',
            'password' => 'password123',
            'role' => 'estudiante',
        ]);

        $this->assertSame('Juan Pablo Pérez Gómez', $user->name);
        $this->assertSame('Juan Pablo Pérez Gómez', $user->nombre_completo);
    }

    public function test_crear_solo_con_name_reparte_las_cuatro_columnas(): void
    {
        $user = User::create([
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'password123',
            'role' => 'estudiante',
        ]);

        $user->refresh();

        $this->assertSame('Juan', $user->primer_nombre);
        $this->assertNull($user->segundo_nombre);
        $this->assertSame('Pérez', $user->primer_apellido);
        $this->assertNull($user->segundo_apellido);
        $this->assertSame('Juan Pérez', $user->name);
    }

    public function test_actualizar_campos_de_nombre_resincroniza_name(): void
    {
        $user = User::create([
            'primer_nombre' => 'Juan',
            'primer_apellido' => 'Pérez',
            'email' => 'juan@example.com',
            'password' => 'password123',
            'role' => 'estudiante',
        ]);

        $user->update([
            'primer_nombre' => 'José',
            'segundo_apellido' => 'Gómez',
        ]);

        $this->assertSame('José Pérez Gómez', $user->fresh()->name);
    }
}
