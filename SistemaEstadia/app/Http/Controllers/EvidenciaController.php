<?php

namespace App\Http\Controllers;

use App\Models\CriterioRubrica;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\User;
use App\Services\RubricaImportService;
use App\Services\RubricaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvidenciaController extends Controller
{
    private const POR_PAGINA = 5;

    public function __construct(
        private readonly RubricaService $rubricas,
        private readonly RubricaImportService $importador,
    ) {}

    public function index(Request $request)
    {
        $usuario = Auth::user();
        $busqueda = trim((string) $request->query('q', ''));
        $asignatura = (string) $request->query('asignatura', '');
        $grupo = (string) $request->query('grupo', '');

        $consulta = $this->evidenciasVisibles()
            ->with(['grupos.asignatura'])
            ->with(['criteriosRubrica' => fn ($q) => $q->orderBy('id_criterio')])
            ->withCount('criteriosRubrica')
            ->withSum('criteriosRubrica as puntaje_rubrica', 'puntaje_maximo')
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

        $evidencias = $consulta->paginate(self::POR_PAGINA)->withQueryString();

        return view('evidencias.index', [
            'user' => $usuario,
            'roleName' => User::roles()[$usuario->role] ?? '',
            'evidencias' => $evidencias,
            // Una rúbrica con evaluaciones de IA asociadas es historial y no se modifica desde el formulario.
            'bloqueadas' => $evidencias->getCollection()
                ->mapWithKeys(fn (Evidencia $e) => [$e->getKey() => $this->rubricas->estaBloqueada($e)]),
            'puntajeTotal' => CriterioRubrica::PUNTAJE_TOTAL,
            'maxCriterios' => CriterioRubrica::MAX_CRITERIOS,
            'criteriosPrevios' => old('criterios', []),
            'grupos' => $grupos,
            'gruposJs' => $grupos->map(fn ($g) => [
                'id' => (string) $g->getKey(),
                'clave' => $g->clave_grupo,
                'asignatura' => (string) $g->id_asignatura,
            ])->values(),
            'asignaturas' => $grupos->pluck('asignatura')->filter()->unique('id_asignatura')->sortBy('nombre_asignatura')->values(),
            'tipos' => Evidencia::tipos(),
            'filtros' => ['q' => $busqueda, 'asignatura' => $asignatura, 'grupo' => $grupo],
            'reabrir' => $modoPrevio ? [
                'modo' => $modoPrevio,
                'action' => $evidenciaPrevia
                    ? route('evidencias.update', $evidenciaPrevia)
                    : route('evidencias.store'),
                'id' => $evidenciaPrevia?->getKey(),
                'bloqueada' => $evidenciaPrevia ? $this->rubricas->estaBloqueada($evidenciaPrevia) : false,
                'tieneRubrica' => $evidenciaPrevia ? $evidenciaPrevia->criteriosRubrica()->exists() : false,
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$datos, $criterios] = $this->validarConRubrica($request, true);

        $evidencia = DB::transaction(function () use ($datos, $criterios) {
            $evidencia = Evidencia::create($this->atributos($datos) + ['id_docente' => Auth::id()]);
            $evidencia->grupos()->sync([(int) $datos['id_grupo']]);
            $this->rubricas->guardar($evidencia, $criterios);

            return $evidencia;
        });

        return redirect()->route('evidencias.index')
            ->with('status', "La evidencia {$evidencia->titulo} se registró correctamente.");
    }

    public function update(Request $request, Evidencia $evidencia): RedirectResponse
    {
        $this->autorizar($evidencia);

        // Con evaluaciones de IA generadas la rúbrica se conserva sin cambios; en los demás casos es obligatoria.
        $bloqueada = $this->rubricas->estaBloqueada($evidencia);
        [$datos, $criterios] = $this->validarConRubrica($request, ! $bloqueada, $evidencia);

        DB::transaction(function () use ($evidencia, $datos, $criterios) {
            $evidencia->update($this->atributos($datos));
            $evidencia->grupos()->sync([(int) $datos['id_grupo']]);
            if ($criterios !== null) {
                $this->rubricas->guardar($evidencia, $criterios);
            }
        });

        return redirect()->route('evidencias.index', $this->filtrosDeRetorno($request))
            ->with('status', "La evidencia {$evidencia->titulo} se actualizó correctamente.");
    }

    /** Lee un archivo de rúbricas (.xlsx/.xls/.csv) y devuelve los criterios para precargar el formulario. */
    public function leerRubrica(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:2048'],
        ], [
            'archivo.required' => 'Selecciona un archivo .xlsx, .xls o .csv.',
            'archivo.file' => 'Selecciona un archivo .xlsx, .xls o .csv.',
            'archivo.mimes' => 'El archivo debe ser .xlsx, .xls o .csv.',
            'archivo.max' => 'El archivo no puede pesar más de 2 MB.',
        ]);

        return response()->json(['rubricas' => array_values($this->importador->leer($request->file('archivo')))]);
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

    /**
     * Valida la evidencia y su rúbrica a la vez para mostrar todos los errores juntos.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>|null}
     *
     * @throws ValidationException
     */
    private function validarConRubrica(Request $request, bool $exigirRubrica, ?Evidencia $actual = null): array
    {
        $errores = [];
        $datos = [];
        $criterios = null;

        try {
            $datos = $this->validar($request, $actual);
        } catch (ValidationException $e) {
            $errores = $e->errors();
        }

        if ($exigirRubrica) {
            if (empty($request->input('criterios'))) {
                $errores['criterios'] = ['La rúbrica es obligatoria: adjunta un archivo (.xlsx, .xls o .csv) o agrega los criterios.'];
            } else {
                try {
                    $criterios = $this->rubricas->validar($request->all());
                } catch (ValidationException $e) {
                    $errores = array_merge($errores, $e->errors());
                }
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return [$datos, $criterios];
    }

    private function validar(Request $request, ?Evidencia $actual = null): array
    {
        $request->merge([
            'titulo' => trim((string) $request->input('titulo')),
            'codigo' => Str::upper(trim((string) $request->input('codigo'))),
        ]);

        return $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'codigo' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9]+(-[A-Z0-9]+)*$/',
                // El código identifica la evidencia al importar rúbricas: no se repite entre las del mismo docente.
                Rule::unique('evidencias', 'codigo')
                    ->where('id_docente', $actual?->id_docente ?? Auth::id())
                    ->ignore($actual?->id_evidencia, 'id_evidencia'),
            ],
            'tipo_evidencia' => ['required', Rule::in(array_keys(Evidencia::tipos()))],
            'id_grupo' => ['required', 'integer', Rule::in($this->gruposDisponibles()->pluck('id_grupo')->all())],
            // Se admiten fechas pasadas: también se registran evidencias de periodos ya entregados.
            'fecha_limite' => ['required', 'date'],
        ], [
            'required' => 'Este campo es obligatorio.',
            'max' => 'No puede exceder :max caracteres.',
            'date' => 'Indica una fecha y hora válidas.',
            'id_grupo.in' => 'Selecciona un grupo válido.',
            'id_grupo.integer' => 'Selecciona un grupo válido.',
            'codigo.unique' => 'Ya tienes otra evidencia con este código.',
            'codigo.regex' => 'Usa solo letras, números y guiones, por ejemplo GDS-EP1.',
        ]);
    }

    private function atributos(array $datos): array
    {
        return [
            'titulo' => $datos['titulo'],
            'codigo' => $datos['codigo'],
            'tipo_evidencia' => $datos['tipo_evidencia'],
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
