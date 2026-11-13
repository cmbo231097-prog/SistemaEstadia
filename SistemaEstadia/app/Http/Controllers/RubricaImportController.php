<?php

namespace App\Http\Controllers;

use App\Models\CriterioRubrica;
use App\Models\Evidencia;
use App\Models\User;
use App\Services\RubricaImportService;
use App\Services\RubricaService;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Importación de rubros desde Excel/CSV: cada evidencia con código coincidente recibe su propio formulario editable.
class RubricaImportController extends Controller
{
    private const SESION = 'rubricas_importadas';

    public function __construct(
        private readonly RubricaImportService $importador,
        private readonly RubricaService $rubricas,
    ) {}

    public function importar(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:2048'],
        ], [
            'archivo.required' => 'Selecciona un archivo .xlsx, .xls o .csv.',
            'archivo.file' => 'Selecciona un archivo .xlsx, .xls o .csv.',
            'archivo.mimes' => 'El archivo debe ser .xlsx, .xls o .csv.',
            'archivo.max' => 'El archivo no puede pesar más de 2 MB.',
        ]);

        $grupos = $this->importador->leer($request->file('archivo'));

        session()->put(self::SESION, [
            'archivo' => $request->file('archivo')->getClientOriginalName(),
            'grupos' => $grupos,
            'ediciones' => [],
            'errores' => [],
            'guardadas' => [],
            'asignaciones' => [],
        ]);

        return redirect()->route('evaluacion-docente.rubricas.revision');
    }

    public function revision()
    {
        $importacion = session(self::SESION);
        if (! $importacion) {
            return redirect()->route('evaluacion-docente.index')
                ->with('error', 'Sube primero un archivo de rúbricas.');
        }

        $evidencias = $this->evidenciasVisibles()
            ->with(['grupos.asignatura'])
            ->withCount('criteriosRubrica')
            ->orderBy('codigo')->orderBy('titulo')
            ->get();

        $asignaturas = $evidencias->flatMap(fn (Evidencia $e) => $e->grupos->pluck('asignatura'))
            ->filter()->unique('id_asignatura')->sortBy('nombre_asignatura')->values();
        $asignadas = [];
        $pendientes = [];
        $formularios = collect();

        foreach ($importacion['grupos'] as $codigo => $grupo) {
            $asignaturasCoincidentes = $asignaturas->filter(
                fn ($a) => $this->normalizar($a->nombre_asignatura) === $this->normalizar($grupo['asignatura'])
            );
            $evidencia = $evidencias->firstWhere('id_evidencia', $importacion['asignaciones'][$codigo] ?? null);

            if (! $evidencia && $asignaturasCoincidentes->count() <= 1) {
                $candidatas = $asignaturasCoincidentes->isNotEmpty()
                    ? $this->deAsignatura($evidencias, $asignaturasCoincidentes->first()->id_asignatura)
                    : $evidencias;
                $porCodigo = $candidatas->filter(fn ($e) => $this->normalizar((string) $e->codigo) === $this->normalizar($codigo));
                if ($asignaturasCoincidentes->isEmpty() && $porCodigo->count() === 1) {
                    $evidencia = $porCodigo->first();
                } elseif ($asignaturasCoincidentes->count() === 1 && $candidatas->count() === 1
                    && ($porCodigo->count() === 1
                        || (! $candidatas->first()->codigo
                            && collect($importacion['grupos'])->filter(fn ($g) => $this->normalizar($g['asignatura']) === $this->normalizar($grupo['asignatura']))->count() === 1))) {
                    $evidencia = $candidatas->first();
                }
            }

            $reservada = $evidencia && ! isset($importacion['asignaciones'][$codigo])
                && in_array($evidencia->id_evidencia, $importacion['asignaciones'] ?? [], true);
            if (! $evidencia || $reservada || isset($asignadas[$evidencia->id_evidencia])) {
                $pendientes[] = ['grupo' => $grupo, 'asignatura' => $asignaturasCoincidentes->count() === 1 ? $asignaturasCoincidentes->first()->id_asignatura : null];
                continue;
            }
            $asignadas[$evidencia->id_evidencia] = true;
            $id = $evidencia->id_evidencia;
            $formularios->push([
                'evidencia' => $evidencia,
                'codigo' => $codigo,
                'criterios' => $importacion['ediciones'][$id] ?? $grupo['criterios'],
                'errores' => $importacion['errores'][$id] ?? [],
                'guardada' => isset($importacion['guardadas'][$id]),
                'bloqueada' => $this->rubricas->estaBloqueada($evidencia),
                'firma' => $this->firmaRubrica($evidencia),
            ]);
        }

        return view('evaluacion-docente.rubricas-importadas', [
            'user' => Auth::user(),
            'roleName' => User::roles()[Auth::user()->role] ?? '',
            'archivo' => $importacion['archivo'],
            'formularios' => $formularios,
            'pendientes' => $pendientes,
            'asignaturas' => $asignaturas,
            'evidenciasParaAsignar' => $evidencias->map(fn (Evidencia $e) => [
                'id' => $e->id_evidencia,
                'titulo' => $e->titulo,
                'codigo' => $e->codigo,
                'grupos' => $e->grupos->pluck('clave_grupo')->join(', '),
                'asignaturas' => $e->grupos->pluck('id_asignatura')->all(),
            ]),
            'puntajeTotal' => CriterioRubrica::PUNTAJE_TOTAL,
            'maxCriterios' => CriterioRubrica::MAX_CRITERIOS,
        ]);
    }

    public function asignar(Request $request): RedirectResponse
    {
        $importacion = session(self::SESION);
        if (! $importacion) {
            return redirect()->route('evaluacion-docente.index')->with('error', 'La importación expiró. Vuelve a subir el archivo.');
        }
        $datos = $request->validate([
            'codigo' => ['required', 'string'],
            'asignatura' => ['required', 'integer'],
            'evidencia' => ['required', 'integer'],
        ]);
        abort_unless(isset($importacion['grupos'][$datos['codigo']]), 404);
        $evidencia = $this->evidenciasVisibles()
            ->whereKey($datos['evidencia'])
            ->whereHas('grupos', fn ($q) => $q->where('id_asignatura', $datos['asignatura']))
            ->firstOrFail();

        if (in_array($evidencia->id_evidencia, $importacion['asignaciones'] ?? [], true)) {
            return redirect()->route('evaluacion-docente.rubricas.revision')
                ->with('error', 'Esa evidencia ya fue seleccionada para otra rúbrica del archivo.');
        }
        $importacion['asignaciones'][$datos['codigo']] = $evidencia->id_evidencia;
        unset($importacion['ediciones'][$evidencia->id_evidencia], $importacion['guardadas'][$evidencia->id_evidencia]);
        session()->put(self::SESION, $importacion);

        return redirect()->route('evaluacion-docente.rubricas.revision');
    }

    public function guardar(Request $request, Evidencia $evidencia): RedirectResponse
    {
        abort_unless(
            $this->esLider() || (int) $evidencia->id_docente === (int) Auth::id(),
            403,
            'Solo puedes configurar la rúbrica de tus propias evidencias.'
        );
        abort_if(
            $this->rubricas->estaBloqueada($evidencia),
            409,
            'La rúbrica no se puede cambiar después de generar evaluaciones; conserva el historial de resultados.'
        );

        $importacion = session(self::SESION);
        if (! $importacion) {
            return redirect()->route('evaluacion-docente.index')
                ->with('error', 'La importación expiró. Vuelve a subir el archivo.');
        }

        $id = $evidencia->id_evidencia;
        $enviados = $request->input('criterios');
        $volver = route('evaluacion-docente.rubricas.revision').'#evidencia-'.$id;

        try {
            $criterios = $this->rubricas->validar(['criterios' => $enviados]);
        } catch (ValidationException $e) {
            // Se conserva lo capturado y el error de esta evidencia para no perder el trabajo del docente.
            session()->put(self::SESION.'.ediciones.'.$id, is_array($enviados) ? array_values($enviados) : []);
            session()->put(self::SESION.'.errores.'.$id, collect($e->errors())->flatten()->unique()->values()->all());

            return redirect($volver);
        }

        DB::transaction(function () use ($request, $evidencia, $criterios) {
            $actual = Evidencia::whereKey($evidencia->getKey())->lockForUpdate()->firstOrFail();
            abort_if($this->rubricas->estaBloqueada($actual), 409, 'La rúbrica está bloqueada por evaluaciones existentes.');
            if ($actual->criteriosRubrica()->exists()) {
                abort_unless(
                    $request->input('confirmar_reemplazo') === '1'
                        && hash_equals($this->firmaRubrica($actual), (string) $request->input('firma_rubrica')),
                    409,
                    'La rúbrica existente requiere confirmación o cambió desde que se abrió el formulario. Recarga y vuelve a confirmar.'
                );
            }
            $this->rubricas->guardar($actual, $criterios);
        });
        session()->put(self::SESION.'.ediciones.'.$id, $criterios);
        session()->forget(self::SESION.'.errores.'.$id);
        session()->put(self::SESION.'.guardadas.'.$id, true);

        return redirect($volver)->with('status', "La rúbrica de «{$evidencia->titulo}» se guardó correctamente.");
    }

    public function descartar(): RedirectResponse
    {
        session()->forget(self::SESION);

        return redirect()->route('evaluacion-docente.index');
    }

    private function esLider(): bool
    {
        return Auth::user()?->role === User::ROLE_LIDER;
    }

    private function evidenciasVisibles()
    {
        return Evidencia::query()->when(! $this->esLider(), fn ($q) => $q->where('id_docente', Auth::id()));
    }

    private function normalizar(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
    }

    private function deAsignatura(Collection $evidencias, int $id): Collection
    {
        return $evidencias->filter(fn (Evidencia $e) => $e->grupos->contains('id_asignatura', $id));
    }

    private function firmaRubrica(Evidencia $evidencia): string
    {
        return hash('sha256', $evidencia->criteriosRubrica()->orderBy('id_criterio')->get()->toJson());
    }
}
