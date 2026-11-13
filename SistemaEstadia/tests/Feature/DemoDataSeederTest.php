<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Equipo;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_puebla_los_crud_relacionales_con_datos_vinculados_y_es_idempotente(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(10, User::count());
        $this->assertGreaterThanOrEqual(10, Asignatura::count());
        $this->assertGreaterThanOrEqual(10, Grupo::count());
        $this->assertDatabaseCount('equipos', 10);
        $this->assertGreaterThanOrEqual(10, Evidencia::count());

        $this->assertDatabaseCount('grupo_estudiantes', 30);
        $this->assertDatabaseCount('equipo_estudiantes', 30);
        $this->assertDatabaseCount('evidencia_grupos', 10);
        $this->assertSame(10, Grupo::query()->whereHas('asignatura')->whereHas('docente')->whereHas('periodo')->count());
        $this->assertSame(10, Evidencia::query()->whereHas('grupos')->count());

        $this->post(route('login.submit'), [
            'email' => 'estudiante01@upemor.edu.mx',
            'password' => 'Demo2026!',
        ])->assertRedirect(route('dashboard'));

        $conteos = [
            User::count(),
            Asignatura::count(),
            Grupo::count(),
            Equipo::count(),
            Evidencia::count(),
        ];

        $this->seed();

        $this->assertSame($conteos, [
            User::count(),
            Asignatura::count(),
            Grupo::count(),
            Equipo::count(),
            Evidencia::count(),
        ]);
    }
}
