<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Models\Evidencia;
use App\Models\User;
use App\Services\ContrasteIndividualPdf;
use App\Services\ContrasteIndividualService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

// Reporte de Contraste Evaluativo Individual (solo Estudiante): compara la calificación del profesor y la de la IA
// para el documento de su propio equipo.
class ContrasteIndividualController extends Controller
{
    public function __construct(
        private readonly ContrasteIndividualService $contraste,
        private readonly ContrasteIndividualPdf $pdf,
    ) {}

    public function index(Request $request)
    {
        [$asignaturas, $asignatura, $evidencias, $evidencia, $equipo] = $this->seleccion($request);
        $reporte = $equipo && $evidencia ? $this->contraste->generar($equipo, $evidencia) : null;

        return view('contraste.index', [
            'user' => Auth::user(),
            'roleName' => trim('Estudiante - '.Auth::user()->matricula, ' -'),
            'asignaturas' => $asignaturas,
            'asignatura' => $asignatura,
            'evidencias' => $evidencias,
            'evidencia' => $evidencia,
            'equipo' => $equipo,
            'reporte' => $reporte,
            'consultaPdf' => $equipo && $evidencia ? ['asignatura' => $asignatura->id_asignatura, 'evidencia' => $evidencia->id_evidencia] : [],
        ]);
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        [, $asignatura, , $evidencia, $equipo] = $this->seleccion($request);
        $volver = fn () => redirect()->route('contraste.index', $request->only(['asignatura', 'evidencia']));

        if (! $equipo || ! $evidencia) {
            return $volver()->with('error', 'Selecciona una asignatura y una evidencia de tu equipo.');
        }
        $reporte = $this->contraste->generar($equipo, $evidencia);
        if ($reporte['estado'] !== ContrasteIndividualService::COMPLETO) {
            return $volver()->with('error', 'El reporte está disponible cuando el profesor y la IA han evaluado el documento de tu equipo.');
        }

        $user = Auth::user();
        $ahora = Carbon::now();
        $contenido = $this->pdf->generar($reporte, [
            'estudiante' => trim($user->nombre.' '.$user->apellidos),
            'matricula' => $user->matricula,
            'asignatura' => $asignatura->nombre_asignatura,
            'grupo' => $equipo->grupo->clave_grupo,
            'equipo' => $equipo->nombre_equipo,
            'profesor' => $equipo->grupo->docente ? trim($equipo->grupo->docente->nombre.' '.$equipo->grupo->docente->apellidos) : 'Sin profesor',
            'evidencia' => $evidencia->titulo,
            'generado_en' => $ahora,
        ]);

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="mi-reporte-de-contraste_'.$ahora->format('Ymd-His').'.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Asignatura → evidencia, siempre dentro de los equipos del propio estudiante. El equipo nunca se toma de la
     * petición: así no se puede consultar el documento de otro equipo.
     */
    private function seleccion(Request $request): array
    {
        $usuario = Auth::user();
        $equipos = Equipo::with('grupo.asignatura', 'grupo.docente')
            ->whereHas('integrantes', fn ($q) => $q->whereKey($usuario->getKey()))
            ->get()
            ->filter(fn (Equipo $e) => $e->grupo?->asignatura);

        $asignaturas = $equipos->pluck('grupo.asignatura')->unique('id_asignatura')->sortBy('nombre_asignatura')->values();
        $asignatura = $asignaturas->firstWhere('id_asignatura', (int) $request->query('asignatura')) ?? $asignaturas->first();
        $equiposAsignatura = $equipos->filter(fn (Equipo $e) => $e->grupo->id_asignatura === $asignatura?->id_asignatura)->values();

        $evidencias = $equiposAsignatura->isEmpty()
            ? collect()
            : Evidencia::whereHas('grupos', fn ($q) => $q->whereIn('grupos.id_grupo', $equiposAsignatura->pluck('id_grupo')))->orderBy('titulo')->get();
        $evidencia = $evidencias->firstWhere('id_evidencia', (int) $request->query('evidencia')) ?? $evidencias->first();

        $equipo = null;
        if ($evidencia) {
            $gruposDeLaEvidencia = $evidencia->grupos()->pluck('grupos.id_grupo');
            $equipo = $equiposAsignatura->first(fn (Equipo $e) => $gruposDeLaEvidencia->contains($e->id_grupo));
        }

        return [$asignaturas, $asignatura, $evidencias, $evidencia, $equipo];
    }
}
