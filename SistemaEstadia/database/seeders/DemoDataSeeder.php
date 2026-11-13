<?php

namespace Database\Seeders;

use App\Models\Asignatura;
use App\Models\Equipo;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $docentes = $this->crearUsuarios([
            ['PROO260001', 'Ana', 'López Ramírez', 'profesora01@upemor.edu.mx'],
            ['PROO260002', 'Carlos', 'Hernández Soto', 'profesor02@upemor.edu.mx'],
            ['PROO260003', 'Mariana', 'Torres García', 'profesora03@upemor.edu.mx'],
        ], User::ROLE_PROFESOR);

        $estudiantes = $this->crearUsuarios([
            ['ESTO260001', 'Valeria', 'Mendoza Cruz', 'estudiante01@upemor.edu.mx'],
            ['ESTO260002', 'Diego', 'Ramírez Flores', 'estudiante02@upemor.edu.mx'],
            ['ESTO260003', 'Camila', 'Sánchez Ríos', 'estudiante03@upemor.edu.mx'],
            ['ESTO260004', 'Mateo', 'García Luna', 'estudiante04@upemor.edu.mx'],
            ['ESTO260005', 'Ximena', 'Pérez Castillo', 'estudiante05@upemor.edu.mx'],
            ['ESTO260006', 'Sebastián', 'Cruz Morales', 'estudiante06@upemor.edu.mx'],
            ['ESTO260007', 'Regina', 'Flores Jiménez', 'estudiante07@upemor.edu.mx'],
            ['ESTO260008', 'Emiliano', 'Vargas Reyes', 'estudiante08@upemor.edu.mx'],
            ['ESTO260009', 'Renata', 'Ortiz Navarro', 'estudiante09@upemor.edu.mx'],
            ['ESTO260010', 'Santiago', 'Castro Vega', 'estudiante10@upemor.edu.mx'],
        ], User::ROLE_ESTUDIANTE);

        $asignaturas = $this->crearAsignaturas();
        $periodo = Periodo::updateOrCreate(
            ['codigo_periodo' => '2026-3'],
            [
                'nombre_periodo' => 'Septiembre - Diciembre 2026',
                'fecha_inicio' => '2026-09-01',
                'fecha_fin' => '2026-12-31',
                'activo' => true,
            ]
        );

        $grupos = $this->crearGrupos($asignaturas, $docentes, $periodo);
        $this->crearEquipos($grupos, $estudiantes);
        $this->crearEvidencias($grupos);
    }

    /**
     * Crea o actualiza usuarios por correo para permitir ejecutar el seeder más de una vez.
     *
     * @param  array<int, array{string, string, string, string}>  $datos
     * @return array<int, User>
     */
    private function crearUsuarios(array $datos, string $rol): array
    {
        $idRol = Role::idOCrearPorClave($rol);

        return collect($datos)->map(function (array $usuario) use ($idRol) {
            [$matricula, $nombre, $apellidos, $correo] = $usuario;

            return User::updateOrCreate(
                ['correo_institucional' => $correo],
                [
                    'matricula' => $matricula,
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'password_hash' => 'Demo2026!',
                    'id_rol' => $idRol,
                    'estatus' => User::ESTATUS_ACTIVO,
                ]
            );
        })->all();
    }

    /**
     * Construye un catálogo variado usando programas válidos del modelo educativo actual.
     *
     * @return array<int, Asignatura>
     */
    private function crearAsignaturas(): array
    {
        $datos = [
            ['Fundamentos de Programación', 'Ingeniería en Tecnologías de la Información e Innovación Digital', 1],
            ['Programación Orientada a Objetos', 'Ingeniería en Tecnologías de la Información e Innovación Digital', 2],
            ['Estructuras de Datos', 'Ingeniería en Tecnologías de la Información e Innovación Digital', 3],
            ['Bases de Datos', 'Ingeniería en Tecnologías de la Información e Innovación Digital', 4],
            ['Redes de Computadoras', 'Ingeniería en Tecnologías de la Información e Innovación Digital', 5],
            ['Ingeniería de Software', 'Ingeniería en Tecnologías de la Información e Innovación Digital', 6],
            ['Seguridad Informática', 'Ingeniería en Tecnologías de la Información e Innovación Digital', 7],
            ['Estadística Aplicada', 'Ingeniería Industrial', 2],
            ['Planeación y Control de la Producción', 'Ingeniería Industrial', 4],
            ['Administración Estratégica', 'Licenciatura en Administración', 6],
        ];

        return collect($datos)->map(function (array $materia) {
            [$nombre, $programa, $cuatrimestre] = $materia;

            $asignatura = Asignatura::query()->firstOrNew([
                'modelo_educativo' => Asignatura::MODELO_ACTUAL,
                'nombre_asignatura' => $nombre,
                'programa_educativo' => $programa,
            ]);

            if (! $asignatura->exists) {
                $asignatura->clave_asignatura = 'TMP-'.Str::upper(Str::random(12));
            }

            $asignatura->cuatrimestre = $cuatrimestre;
            $asignatura->save();
            $asignatura->clave_asignatura = $this->claveAsignatura($asignatura, $programa);
            $asignatura->save();

            return $asignatura;
        })->all();
    }

    private function claveAsignatura(Asignatura $asignatura, string $programa): string
    {
        $abreviatura = Asignatura::abreviaturaDePrograma(Asignatura::MODELO_ACTUAL, $programa);
        $base = $abreviatura.'-'.str_pad((string) $asignatura->id_asignatura, 4, '0', STR_PAD_LEFT);
        $clave = $base;
        $sufijo = 1;

        while (Asignatura::query()->where('clave_asignatura', $clave)
            ->where('id_asignatura', '!=', $asignatura->id_asignatura)
            ->exists()) {
            $clave = $base.'-'.$sufijo++;
        }

        return $clave;
    }

    /**
     * Asigna diez grupos a materias y docentes, evitando duplicados al resembrar.
     *
     * @param  array<int, Asignatura>  $asignaturas
     * @param  array<int, User>  $docentes
     * @return array<int, Grupo>
     */
    private function crearGrupos(array $asignaturas, array $docentes, Periodo $periodo): array
    {
        return collect($asignaturas)->map(function (Asignatura $asignatura, int $indice) use ($docentes, $periodo) {
            return Grupo::updateOrCreate(
                [
                    'clave_grupo' => sprintf('%02dA', $indice + 1),
                    'id_asignatura' => $asignatura->id_asignatura,
                    'id_periodo' => $periodo->id_periodo,
                ],
                ['id_docente' => $docentes[$indice % count($docentes)]->id_usuario]
            );
        })->all();
    }

    /**
     * Registra un equipo de tres estudiantes por grupo y mantiene ambas matrículas coherentes.
     *
     * @param  array<int, Grupo>  $grupos
     * @param  array<int, User>  $estudiantes
     */
    private function crearEquipos(array $grupos, array $estudiantes): void
    {
        foreach ($grupos as $indice => $grupo) {
            $equipo = Equipo::updateOrCreate(
                [
                    'nombre_equipo' => sprintf('Equipo de Proyecto %02d', $indice + 1),
                    'id_grupo' => $grupo->id_grupo,
                ]
            );

            $integrantes = collect(range(0, 2))
                ->map(fn (int $offset) => $estudiantes[($indice * 3 + $offset) % count($estudiantes)]->id_usuario)
                ->all();

            $equipo->integrantes()->sync($integrantes);

            foreach ($integrantes as $idEstudiante) {
                DB::table('grupo_estudiantes')->updateOrInsert([
                    'id_grupo' => $grupo->id_grupo,
                    'id_estudiante' => $idEstudiante,
                ]);
            }
        }
    }

    /**
     * Crea una actividad distinta para cada grupo con su docente y fecha límite futura.
     *
     * @param  array<int, Grupo>  $grupos
     */
    private function crearEvidencias(array $grupos): void
    {
        $tipos = array_keys(Evidencia::tipos());

        foreach ($grupos as $indice => $grupo) {
            $asignatura = $grupo->asignatura;
            $titulo = sprintf('Actividad %02d: %s', $indice + 1, $asignatura->nombre_asignatura);
            $evidencia = Evidencia::updateOrCreate(
                [
                    'titulo' => $titulo,
                    'id_docente' => $grupo->id_docente,
                ],
                [
                    'codigo' => sprintf('DEMO-EP%02d', $indice + 1),
                    'tipo_evidencia' => $tipos[$indice % count($tipos)],
                    'descripcion_entregables' => "Entrega académica para {$asignatura->nombre_asignatura}: documento con objetivos, desarrollo, conclusiones y referencias consultadas.",
                    'fecha_limite' => now()->addDays(14 + $indice)->setTime(23, 59),
                ]
            );

            $evidencia->grupos()->sync([$grupo->id_grupo]);
        }
    }
}
