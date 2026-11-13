<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\ComparativaService;
use App\Services\ReporteDiscrepanciaPdf;
use App\Services\ReporteDiscrepanciaService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

// Reporte de Discrepancia Evaluativa (solo Líder de Proyecto): docente vs. Gemini, filtrable por fechas y profesor.
class ReporteDiscrepanciaController extends Controller
{
    public function __construct(
        private readonly ReporteDiscrepanciaService $reporte,
        private readonly ReporteDiscrepanciaPdf $pdf,
        private readonly ComparativaService $comparativas,
    ) {}

    public function index(Request $request)
    {
        $validador = $this->validador($request);
        $filtros = $validador->fails() ? ['desde' => null, 'hasta' => null, 'profesor' => null] : $this->filtros($request);

        return view('reportes.discrepancia', [
            'user' => Auth::user(),
            'roleName' => User::roles()[Auth::user()->role] ?? '',
            'profesores' => $this->profesores(),
            'valores' => $request->only(['desde', 'hasta', 'profesor']),
            'errores' => $validador->errors(),
            'reporte' => $validador->fails()
                ? ['filas' => collect(), 'grupos' => collect(), 'resumen' => null]
                : $this->reporte->generar($filtros['desde'], $filtros['hasta'], $filtros['profesor']),
            'umbral' => $this->comparativas->umbralAlerta(),
            'consultaPdf' => array_filter($request->only(['desde', 'hasta', 'profesor']), fn ($v) => filled($v)),
        ]);
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        $validador = $this->validador($request);
        if ($validador->fails()) {
            return redirect()->route('reportes.discrepancia.index', $request->only(['desde', 'hasta', 'profesor']))
                ->withErrors($validador);
        }

        $filtros = $this->filtros($request);
        $datos = $this->reporte->generar($filtros['desde'], $filtros['hasta'], $filtros['profesor']);
        if ($datos['resumen'] === null) {
            return redirect()->route('reportes.discrepancia.index', $request->only(['desde', 'hasta', 'profesor']))
                ->with('error', 'No hay comparaciones para los filtros seleccionados; no se generó el PDF.');
        }

        $profesor = $filtros['profesor'] ? $this->profesores()->firstWhere('id_usuario', $filtros['profesor']) : null;
        $ahora = Carbon::now();
        $contenido = $this->pdf->generar($datos, [
            'desde' => $filtros['desde'],
            'hasta' => $filtros['hasta'],
            'profesor' => $profesor ? trim($profesor->nombre.' '.$profesor->apellidos) : null,
            'generado_por' => trim(Auth::user()->nombre.' '.Auth::user()->apellidos),
            'generado_en' => $ahora,
        ], $this->comparativas->umbralAlerta());

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reporte-discrepancia_'.$ahora->format('Ymd-His').'.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function validador(Request $request)
    {
        return Validator::make($request->all(), [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'profesor' => ['nullable', 'integer', Rule::exists('usuarios', 'id_usuario')->where('id_rol', Role::idPorClave(User::ROLE_PROFESOR) ?? 0)],
        ], [
            'desde.date_format' => 'La fecha inicial no es válida.',
            'hasta.date_format' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'profesor.exists' => 'Selecciona un profesor de la lista.',
            'profesor.integer' => 'Selecciona un profesor de la lista.',
        ]);
    }

    /** @return array{desde: ?Carbon, hasta: ?Carbon, profesor: ?int} */
    private function filtros(Request $request): array
    {
        return [
            'desde' => $request->filled('desde') ? Carbon::createFromFormat('Y-m-d', $request->input('desde'))->startOfDay() : null,
            'hasta' => $request->filled('hasta') ? Carbon::createFromFormat('Y-m-d', $request->input('hasta'))->endOfDay() : null,
            'profesor' => $request->filled('profesor') ? (int) $request->input('profesor') : null,
        ];
    }

    private function profesores()
    {
        return User::query()
            ->where('id_rol', Role::idPorClave(User::ROLE_PROFESOR) ?? 0)
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->get();
    }
}
