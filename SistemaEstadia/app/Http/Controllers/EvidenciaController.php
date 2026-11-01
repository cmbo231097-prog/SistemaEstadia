<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EvidenciaController extends Controller
{
    private const POR_PAGINA = 5;

    public function index(Request $request)
    {
        $usuario = Auth::user();
        $busqueda = trim((string) $request->query('q', ''));
        $asignatura = (string) $request->query('asignatura', '');
        $grupo = (string) $request->query('grupo', '');

        $consulta = $this->evidenciasVisibles()
            ->with(['grupos.asignatura'])
            ->orderByDesc('id_evidencia');

        if ($busqueda !== '') {
            $consulta->where('titulo', 'like', "%{$busqueda}%");
        }

        if (ctype_digit($asignatura) && $asignatura !== '') {
            $consulta->whereHas('grupos', fn ($q) => $q->where('id_asignatura', (int) $asignatura));
        }

        if (ctype_digit($grupo) && $grupo !== '') {
            $consulta->whereHas('grupos', fn ($q) => $q->where('grupos.id_grupo', (int) $grupo));
        }

        $grupos = $this->gruposDisponibles();
        $modoPrevio = $request->old('_modo');
        $evidenciaPrevia = $modoPrevio === 'editar' ? Evidencia::find($request->old('_id')) : null;

        return view('evidencias.index', [
            'user' => $usuario,
            'roleName' => User::roles()[$usuario->role] ?? '',
            'evidencias' => $consulta->paginate(self::POR_PAGINA)->withQueryString(),
            'grupos' => $grupos,
            'gruposJs' => $grupos->map(fn ($g) => [
                'id' => (string) $g->getKey(),
                'clave' => $g->clave_grupo,
                'asignatura' => (string) $g->id_asignatura,
            ])->values(),
            'asignaturas' => $grupos->pluck('asignatura')->filter()->unique('id_asignatura')->sortBy('nombre_asignatura')->values(),
            'tipos' => Evidencia::tipos(),
            'niveles' => Evidencia::niveles(),
            'filtros' => ['q' => $busqueda, 'asignatura' => $asignatura, 'grupo' => $grupo],
            'reabrir' => $modoPrevio ? [
                'modo' => $modoPrevio,
                'action' => $evidenciaPrevia
                    ? route('evidencias.update', $evidenciaPrevia)
                    : route('evidencias.store'),
                'id' => $evidenciaPrevia?->getKey(),
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request, true);

        $evidencia = DB::transaction(function () use ($datos) {
            $evidencia = Evidencia::create($this->atributos($datos) + ['id_docente' => Auth::id()]);
            $evidencia->grupos()->sync([(int) $datos['id_grupo']]);

            return $evidencia;
        });

        return redirect()->route('evidencias.index')
            ->with('status', "La evidencia {$evidencia->titulo} se registró correctamente.");
    }

    public function update(Request $request, Evidencia $evidencia): RedirectResponse
    {
        $this->autorizar($evidencia);
        $datos = $this->validar($request, false);

        DB::transaction(function () use ($evidencia, $datos) {
            $evidencia->update($this->atributos($datos));
            $evidencia->grupos()->sync([(int) $datos['id_grupo']]);
        });

        return redirect()->route('evidencias.index', $this->filtrosDeRetorno($request))
            ->with('status', "La evidencia {$evidencia->titulo} se actualizó correctamente.");
    }

    public function destroy(Request $request, Evidencia $evidencia): RedirectResponse
    {
        $this->autorizar($evidencia);
        $volver = redirect()->route('evidencias.index', $this->filtrosDeRetorno($request));
        $titulo = $evidencia->titulo;

        try {
            $evidencia->delete();
        } catch (QueryException) {
            return $volver->with('error', "No se puede eliminar {$titulo} porque ya tiene documentos o evaluaciones vinculados.");
        }

        return $volver->with('status', "La evidencia {$titulo} se eliminó correctamente.");
    }

    private function esLider(): bool
    {
        return Auth::user()?->role === User::ROLE_LIDER;
    }

    private function evidenciasVisibles()
    {
        return Evidencia::query()->when(! $this->esLider(), fn ($q) => $q->where('id_docente', Auth::id()));
    }

    private function autorizar(Evidencia $evidencia): void
    {
        abort_unless($this->esLider() || (int) $evidencia->id_docente === (int) Auth::id(), 403, 'Solo puedes gestionar tus propias evidencias.');
    }

    /** El profesor solo ve sus grupos asignados; el Líder los ve todos. */
    private function gruposDisponibles()
    {
        return Grupo::query()
            ->with('asignatura')
            ->when(! $this->esLider(), fn ($q) => $q->where('id_docente', Auth::id()))
            ->orderBy('clave_grupo')
            ->get();
    }

    private function validar(Request $request, bool $alta): array
    {
        $request->merge([
            'titulo' => trim((string) $request->input('titulo')),
            'entregables' => trim((string) $request->input('entregables')),
        ]);

        return $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'tipo_evidencia' => ['required', Rule::in(array_keys(Evidencia::tipos()))],
            'nivel_retroalimentacion_ia' => ['required', Rule::in(array_keys(Evidencia::niveles()))],
            'entregables' => ['required', 'string', 'max:2000'],
            'id_grupo' => ['required', 'integer', Rule::in($this->gruposDisponibles()->pluck('id_grupo')->all())],
            'fecha_limite' => array_filter(['required', 'date', $alta ? 'after:now' : null]),
        ], [
            'required' => 'Este campo es obligatorio.',
            'max' => 'No puede exceder :max caracteres.',
            'date' => 'Indica una fecha y hora válidas.',
            'fecha_limite.after' => 'La fecha límite debe ser posterior a la fecha actual.',
            'id_grupo.in' => 'Selecciona un grupo válido.',
            'id_grupo.integer' => 'Selecciona un grupo válido.',
        ]);
    }

    private function atributos(array $datos): array
    {
        return [
            'titulo' => $datos['titulo'],
            'tipo_evidencia' => $datos['tipo_evidencia'],
            'nivel_retroalimentacion_ia' => $datos['nivel_retroalimentacion_ia'],
            'descripcion_entregables' => $datos['entregables'],
            'fecha_limite' => $datos['fecha_limite'],
        ];
    }

    private function filtrosDeRetorno(Request $request): array
    {
        return array_filter([
            'q' => $request->input('q'),
            'asignatura' => $request->input('filtro_asignatura'),
            'grupo' => $request->input('filtro_grupo'),
            'page' => $request->input('page'),
        ], fn ($valor) => filled($valor));
    }
}
