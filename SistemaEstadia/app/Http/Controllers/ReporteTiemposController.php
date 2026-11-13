<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\User;
use App\Services\ReporteTiemposExcel;
use App\Services\ReporteTiemposService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

// Reporte de Eficiencia de Tiempos (solo Líder de Proyecto): revisión docente vs. respuesta de la API, en Excel.
class ReporteTiemposController extends Controller
{
    public function __construct(
        private readonly ReporteTiemposService $reporte,
        private readonly ReporteTiemposExcel $excel,
    ) {}

    public function index(Request $request)
    {
        $validador = $this->validador($request);
        $filtros = $validador->fails() ? ['desde' => null, 'hasta' => null, 'asignatura' => null] : $this->filtros($request);

        return view('reportes.tiempos', [
            'user' => Auth::user(),
            'roleName' => User::roles()[Auth::user()->role] ?? '',
            'asignaturas' => Asignatura::orderBy('nombre_asignatura')->get(),
            'valores' => $request->only(['desde', 'hasta', 'asignatura']),
            'errores' => $validador->errors(),
            'reporte' => $validador->fails()
                ? ['filas' => collect(), 'grupos' => collect(), 'resumen' => null]
                : $this->reporte->generar($filtros['desde'], $filtros['hasta'], $filtros['asignatura']),
            'consultaExcel' => array_filter($request->only(['desde', 'hasta', 'asignatura']), fn ($v) => filled($v)),
        ]);
    }

    public function excel(Request $request): Response|RedirectResponse
    {
        $volver = fn () => redirect()->route('reportes.tiempos.index', $request->only(['desde', 'hasta', 'asignatura']));

        $validador = $this->validador($request);
        if ($validador->fails()) {
            return $volver()->withErrors($validador);
        }

        $filtros = $this->filtros($request);
        $datos = $this->reporte->generar($filtros['desde'], $filtros['hasta'], $filtros['asignatura']);
        if ($datos['resumen'] === null) {
            return $volver()->with('error', 'No hay documentos con evaluación del docente y de Gemini para los filtros seleccionados; no se generó el Excel.');
        }

        $asignatura = $filtros['asignatura'] ? Asignatura::find($filtros['asignatura']) : null;
        $ahora = Carbon::now();
        $contenido = $this->excel->generar($datos, [
            'desde' => $filtros['desde'],
            'hasta' => $filtros['hasta'],
            'asignatura' => $asignatura?->nombre_asignatura,
            'generado_por' => trim(Auth::user()->nombre.' '.Auth::user()->apellidos),
            'generado_en' => $ahora,
        ]);

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="reporte-eficiencia-tiempos_'.$ahora->format('Ymd-His').'.xlsx"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function validador(Request $request)
    {
        return Validator::make($request->all(), [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'asignatura' => ['nullable', 'integer', Rule::exists('asignaturas', 'id_asignatura')],
        ], [
            'desde.date_format' => 'La fecha inicial no es válida.',
            'hasta.date_format' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'asignatura.exists' => 'Selecciona una asignatura de la lista.',
            'asignatura.integer' => 'Selecciona una asignatura de la lista.',
        ]);
    }

    /** @return array{desde: ?Carbon, hasta: ?Carbon, asignatura: ?int} */
    private function filtros(Request $request): array
    {
        return [
            'desde' => $request->filled('desde') ? Carbon::createFromFormat('Y-m-d', $request->input('desde'))->startOfDay() : null,
            'hasta' => $request->filled('hasta') ? Carbon::createFromFormat('Y-m-d', $request->input('hasta'))->endOfDay() : null,
            'asignatura' => $request->filled('asignatura') ? (int) $request->input('asignatura') : null,
        ];
    }
}
