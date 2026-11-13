<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\User;
use App\Services\RendimientoGrupalExcel;
use App\Services\RendimientoGrupalService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

// Reporte de Rendimiento Grupal: el profesor consulta calificaciones y desempeño por rubro (docente vs. IA) de su grupo.
class RendimientoGrupalController extends Controller
{
    public function __construct(
        private readonly RendimientoGrupalService $reporte,
        private readonly RendimientoGrupalExcel $excel,
    ) {}

    public function index(Request $request)
    {
        [$asignaturas, $gruposAsignatura, $evidencias, $asignatura, $grupo, $evidencia] = $this->seleccion($request);

        return view('rendimiento-grupal.index', [
            'user' => Auth::user(),
            'roleName' => User::roles()[Auth::user()->role] ?? '',
            'asignaturas' => $asignaturas,
            'grupos' => $gruposAsignatura,
            'evidencias' => $evidencias,
            'asignatura' => $asignatura,
            'grupo' => $grupo,
            'evidencia' => $evidencia,
            'reporte' => $grupo && $evidencia ? $this->reporte->generar($grupo, $evidencia) : null,
            'consultaExcel' => $grupo && $evidencia ? ['grupo' => $grupo->id_grupo, 'evidencia' => $evidencia->id_evidencia] : [],
        ]);
    }

    public function excel(Request $request): Response|RedirectResponse
    {
        $datos = $request->validate([
            'grupo' => ['required', 'integer'],
            'evidencia' => ['required', 'integer'],
        ]);

        $grupo = $this->gruposDisponibles()->firstWhere('id_grupo', (int) $datos['grupo']);
        abort_unless($grupo, 403, 'No puedes consultar este grupo.');
        $evidencia = $this->evidenciasVisibles()
            ->whereKey($datos['evidencia'])
            ->whereHas('grupos', fn ($q) => $q->where('grupos.id_grupo', $grupo->id_grupo))
            ->firstOrFail();

        $reporte = $this->reporte->generar($grupo, $evidencia);
        if ($reporte['equipos']->isEmpty()) {
            return redirect()->route('rendimiento-grupal.index', ['asignatura' => $grupo->id_asignatura, 'grupo' => $grupo->id_grupo, 'evidencia' => $evidencia->id_evidencia])
                ->with('error', 'Este grupo no tiene documentos cargados para la evidencia; no se generó el Excel.');
        }

        $ahora = Carbon::now();
        $contenido = $this->excel->generar($reporte, [
            'asignatura' => $grupo->asignatura?->nombre_asignatura ?? 'Sin asignatura',
            'grupo' => $grupo->clave_grupo,
            'evidencia' => $evidencia->titulo,
            'generado_por' => trim(Auth::user()->nombre.' '.Auth::user()->apellidos),
            'generado_en' => $ahora,
        ]);

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="reporte-rendimiento-grupal_'.$ahora->format('Ymd-His').'.xlsx"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Misma cascada que «Carga» y «Evaluación con IA»: asignatura → grupo → evidencia, con el primero por defecto. */
    private function seleccion(Request $request): array
    {
        $grupos = $this->gruposDisponibles();
        $asignaturas = $grupos->pluck('asignatura')->filter()->unique('id_asignatura')->values();

        $asignatura = $asignaturas->firstWhere('id_asignatura', (int) $request->query('asignatura')) ?? $asignaturas->first();
        $gruposAsignatura = $grupos->where('id_asignatura', $asignatura?->id_asignatura)->values();
        $grupo = $gruposAsignatura->firstWhere('id_grupo', (int) $request->query('grupo')) ?? $gruposAsignatura->first();

        $evidencias = $grupo
            ? $this->evidenciasVisibles()->whereHas('grupos', fn ($q) => $q->where('grupos.id_grupo', $grupo->id_grupo))->orderBy('titulo')->get()
            : collect();
        $evidencia = $evidencias->firstWhere('id_evidencia', (int) $request->query('evidencia')) ?? $evidencias->first();

        return [$asignaturas, $gruposAsignatura, $evidencias, $asignatura, $grupo, $evidencia];
    }

    private function esLider(): bool
    {
        return Auth::user()?->role === User::ROLE_LIDER;
    }

    private function evidenciasVisibles()
    {
        return Evidencia::query()->when(! $this->esLider(), fn ($q) => $q->where('id_docente', Auth::id()));
    }

    private function gruposDisponibles()
    {
        return Grupo::query()
            ->with('asignatura')
            ->when(! $this->esLider(), fn ($q) => $q->where('id_docente', Auth::id()))
            ->orderBy('clave_grupo')
            ->get();
    }
}
