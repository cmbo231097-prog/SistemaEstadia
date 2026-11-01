<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isRole(User::ROLE_LIDER)) {
            return view('dashboard.lider', [
                'user' => $user,
                'roleName' => $this->roleName($user->role),
                ...$this->datosLider(),
            ]);
        }

        if ($user->isRole(User::ROLE_PROFESOR)) {
            return view('dashboard.profesor', [
                'user' => $user,
                'roleName' => 'Profesor Titular',
                ...$this->datosProfesor(),
            ]);
        }

        if ($user->isRole(User::ROLE_ESTUDIANTE)) {
            return view('dashboard.estudiante', [
                'user' => $user,
                'roleName' => trim('Estudiante - '.$user->matricula, ' -'),
                ...$this->datosEstudiante(),
            ]);
        }

        return view('dashboard.index', [
            'user' => $user,
            'roleName' => $this->roleName($user->role),
        ]);
    }

    /**
     * Datos de ejemplo del panel del Estudiante.
     * Se reemplazarán por consultas reales cuando se programen los módulos.
     */
    private function datosEstudiante(): array
    {
        $evaluaciones = [
            ['evidencia' => 'Práctica 3: Árboles Binarios (Estructuras de Datos)', 'asignatura' => 'Estructuras de Datos', 'equipo' => 'Equipo 2 (ITI 9°A)', 'calif_docente' => 9.5, 'calif_ia' => 9.2],
            ['evidencia' => 'Reporte Técnico: Normalización de Base de Datos', 'asignatura' => 'Bases de Datos', 'equipo' => 'Equipo 2 (ITI 9°A)', 'calif_docente' => 9.0, 'calif_ia' => 8.8],
            ['evidencia' => 'Proyecto: Control de Sesiones y Seguridad', 'asignatura' => 'Desarrollo Web', 'equipo' => 'Equipo 2 (ITI 9°A)', 'calif_docente' => 8.8, 'calif_ia' => 8.5],
            ['evidencia' => 'Ensayo: Impacto de Modelos de Lenguaje en TI', 'asignatura' => 'Seminario de TI', 'equipo' => 'Equipo 2 (ITI 9°A)', 'calif_docente' => 9.5, 'calif_ia' => 9.0],
        ];

        $total = max(count($evaluaciones), 1);
        $docente = array_sum(array_column($evaluaciones, 'calif_docente')) / $total;
        $ia = array_sum(array_column($evaluaciones, 'calif_ia')) / $total;
        // Diferencia absoluta media sobre la escala de 10 puntos, expresada en porcentaje.
        $discrepancia = array_sum(array_map(fn ($e) => abs($e['calif_ia'] - $e['calif_docente']), $evaluaciones)) / $total * 10;

        return [
            'kpis' => [
                'evaluadas' => count($evaluaciones),
                'promedio_docente' => $docente,
                'promedio_ia' => $ia,
                'discrepancia' => $discrepancia,
                'coherencia' => $discrepancia <= 5 ? 'alta' : ($discrepancia <= 10 ? 'media' : 'baja'),
            ],
            'asignaturas' => array_values(array_unique(array_column($evaluaciones, 'asignatura'))),
            'evaluaciones' => $evaluaciones,
        ];
    }

    /**
     * Datos de ejemplo del panel del Profesor Titular.
     * Cada PDF corresponde a un equipo de 3 alumnos (un grupo de 30 genera máximo 10 PDFs).
     * Se reemplazarán por consultas reales cuando se programen los módulos.
     */
    private function datosProfesor(): array
    {
        $grupos = [
            ['grupo' => 'ITI 9°A', 'alumnos' => 27],
            ['grupo' => 'ITI 9°B', 'alumnos' => 30],
            ['grupo' => 'ITI 8°C', 'alumnos' => 27],
        ];

        $actividades = [
            ['evidencia' => 'Práctica 3: Árboles Binarios (Estructuras de Datos)', 'asignatura' => 'Estructuras de Datos', 'grupo' => 'ITI 9°A', 'pdfs' => 9, 'sanitizados' => 9, 'calificados' => 9, 'estado_ia' => 'listo'],
            ['evidencia' => 'Reporte Técnico: Normalización de Base de Datos', 'asignatura' => 'Bases de Datos', 'grupo' => 'ITI 9°B', 'pdfs' => 10, 'sanitizados' => 10, 'calificados' => 6, 'estado_ia' => 'listo'],
            ['evidencia' => 'Proyecto: Control de Sesiones y Seguridad', 'asignatura' => 'Desarrollo Web', 'grupo' => 'ITI 9°A', 'pdfs' => 8, 'sanitizados' => 8, 'calificados' => 8, 'estado_ia' => 'listo'],
            ['evidencia' => 'Ensayo: Ética y Sesgo en Modelos LLM', 'asignatura' => 'Seminario de TI', 'grupo' => 'ITI 8°C', 'pdfs' => 9, 'sanitizados' => 9, 'calificados' => 0, 'estado_ia' => 'procesando'],
        ];

        $subidos = array_sum(array_column($actividades, 'pdfs'));
        $calificados = array_sum(array_column($actividades, 'calificados'));
        $alumnos = array_sum(array_column($grupos, 'alumnos'));

        return [
            'kpis' => [
                'grupos' => count($grupos),
                'alumnos' => $alumnos,
                'equipos' => intdiv($alumnos, 3),
                'pdfs_subidos' => $subidos,
                'pdfs_sanitizados' => array_sum(array_column($actividades, 'sanitizados')),
                'pdfs_pendientes' => $subidos - $calificados,
                'tiempo_medio' => 5.8,
            ],
            'actividades' => $actividades,
            'infra' => ['api_en_linea' => true],
        ];
    }

    /**
     * Datos de ejemplo del panel del Líder de Proyecto.
     * Se reemplazarán por consultas reales cuando se programen los módulos.
     */
    private function datosLider(): array
    {
        return [
            'kpis' => [
                'sanitizados' => 142,
                'total_corpus' => 150,
                'evaluados_ia' => 124,
                'en_cola' => 18,
                'discrepancia' => 7.8,
                'discrepancia_nota' => 'Rango de tolerancia aceptable',
                'eficiencia' => 86.4,
            ],
            'lotes' => [
                ['evidencia' => 'Práctica 3: Árboles Binarios (Estructuras)', 'profesor' => 'Ing. Braulio Cuevas', 'sanitizados' => 18, 'total' => 18, 'estado_ia' => 'evaluado', 'progreso' => 100],
                ['evidencia' => 'Ensayo: Impacto de LLMs (Seminario TI)', 'profesor' => 'Dra. Irma Hernández', 'sanitizados' => 20, 'total' => 20, 'estado_ia' => 'procesando', 'progreso' => 70],
                ['evidencia' => 'Proyecto: Arquitectura MVC (Desarrollo Web)', 'profesor' => 'Ing. Braulio Cuevas', 'sanitizados' => 0, 'total' => 18, 'estado_ia' => 'espera', 'progreso' => 0],
                ['evidencia' => 'Reporte Técnico: Normalización de BD', 'profesor' => 'Mtro. Morales Gómez', 'sanitizados' => 22, 'total' => 22, 'estado_ia' => 'evaluado', 'progreso' => 100],
            ],
            'infra' => [
                'api_en_linea' => true,
                'ultimo_respaldo' => 'Hoy, 08:30 hrs',
                'notificaciones' => 1,
            ],
        ];
    }

    private function roleName(?string $role): string
    {
        return User::roles()[$role] ?? 'Estudiante';
    }
}
