<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AsignaturaController extends Controller
{
    /** Número de asignaturas mostradas por página en el catálogo. */
    private const POR_PAGINA = 5;

    /**
     * Muestra el catálogo aplicando búsqueda, filtros y paginación.
     */
    public function index(Request $request)
    {
        $busqueda = trim((string) $request->query('q', ''));
        $modelo = trim((string) $request->query('modelo_educativo', ''));
        $programa = trim((string) $request->query('programa', ''));
        $cuatrimestre = (string) $request->query('cuatrimestre', '');
        [$modeloPrograma, $abreviaturaPrograma] = array_pad(explode('|', $programa, 2), 2, null);

        $consulta = Asignatura::query()->withCount('grupos')->orderBy('clave_asignatura');

        // Solo se aplican modelos conocidos para ignorar valores de filtro inválidos.
        if (array_key_exists($modelo, Asignatura::modelosEducativos())) {
            $consulta->where('modelo_educativo', $modelo);
        }

        if ($busqueda !== '') {
            $consulta->where(function ($q) use ($busqueda) {
                $q->where('clave_asignatura', 'like', "%{$busqueda}%")
                    ->orWhere('nombre_asignatura', 'like', "%{$busqueda}%")
                    ->orWhere('programa_educativo', 'like', "%{$busqueda}%");
            });
        }

        if ($modeloPrograma && $abreviaturaPrograma) {
            $programaNombre = Asignatura::programasEducativos($modeloPrograma)[$abreviaturaPrograma] ?? null;
            if ($programaNombre !== null) {
                $consulta->where('modelo_educativo', $modeloPrograma)
                    ->where('programa_educativo', $programaNombre);
            } else {
                // Un filtro no reconocido debe devolver cero resultados, no el catálogo completo.
                $consulta->where('id_asignatura', 0);
            }
        } elseif ($programa !== '') {
            $consulta->where('id_asignatura', 0);
        }

        if (filter_var($cuatrimestre, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]])) {
            $consulta->where('cuatrimestre', (int) $cuatrimestre);
        }

        $modoPrevio = $request->old('_modo');
        $asignaturaPrevia = $modoPrevio === 'editar' ? Asignatura::find($request->old('_id')) : null;

        // Se conservan los datos anteriores para reabrir el formulario tras un error de validación.
        return view('admin.asignaturas.index', [
            'user' => Auth::user(),
            'roleName' => 'Líder de proyecto',
            'asignaturas' => $consulta->paginate(self::POR_PAGINA)->withQueryString(),
            'modelos' => Asignatura::modelosEducativos(),
            'programas' => Asignatura::programasEducativos(),
            'filtros' => ['q' => $busqueda, 'modelo' => $modelo, 'programa' => $programa, 'cuatrimestre' => $cuatrimestre],
            'cuatrimestres' => range(1, 10),
            'reabrir' => $modoPrevio ? [
                'modo' => $modoPrevio,
                'action' => $asignaturaPrevia
                    ? route('admin.asignaturas.update', $asignaturaPrevia)
                    : route('admin.asignaturas.store'),
                'id' => $asignaturaPrevia?->getKey(),
            ] : null,
        ]);
    }

    /** Valida y registra la asignatura, generando una clave vinculada a su ID. */
    public function store(Request $request): RedirectResponse
    {
        $this->normalizar($request);
        $datos = $request->validate($this->reglas(), $this->mensajes());

        $asignatura = DB::transaction(function () use ($datos) {
            // La clave final necesita el ID autogenerado, por eso se crea primero una clave temporal.
            $asignatura = Asignatura::create($this->atributos($datos) + [
                'clave_asignatura' => 'TMP-'.Str::upper(Str::random(12)),
            ]);
            $asignatura->update([
                'clave_asignatura' => $this->generarClave(
                    $asignatura->id_asignatura,
                    $datos['modelo_educativo'],
                    $datos['programa_educativo']
                ),
            ]);

            return $asignatura;
        });

        return redirect()->route('admin.asignaturas.index')
            ->with('status', "La asignatura {$asignatura->nombre_asignatura} se registró correctamente con la clave {$asignatura->clave_asignatura}.");
    }

    /** Actualiza los datos y conserva los filtros usados para volver al catálogo. */
    public function update(Request $request, Asignatura $asignatura): RedirectResponse
    {
        $this->normalizar($request);
        $datos = $request->validate($this->reglas(), $this->mensajes());

        $asignatura->update($this->atributos($datos) + [
            'clave_asignatura' => $this->generarClave(
                $asignatura->id_asignatura,
                $datos['modelo_educativo'],
                $datos['programa_educativo'],
                $asignatura->id_asignatura
            ),
        ]);

        return redirect()->route('admin.asignaturas.index', $this->filtrosDeRetorno($request))
            ->with('status', "La asignatura {$asignatura->nombre_asignatura} se actualizó correctamente.");
    }

    /**
    * Elimina la asignatura o informa si existen grupos o evidencias relacionados.
    *
    * @param  Request     $request     Solicitud con los filtros vigentes del catálogo.
    * @param  Asignatura  $asignatura  Asignatura que se desea eliminar.
    * @return RedirectResponse         Redirección al catálogo con mensaje de éxito o error.
    */
    public function destroy(Request $request, Asignatura $asignatura): RedirectResponse
    {
        $volver = redirect()->route('admin.asignaturas.index', $this->filtrosDeRetorno($request));
        $nombre = $asignatura->nombre_asignatura;

        try {
            $asignatura->delete();
        } catch (QueryException) {
            return $volver->with('error', "No se puede eliminar {$nombre} porque tiene grupos o evidencias vinculados.");
        }

        return $volver->with('status', "La asignatura {$nombre} se eliminó correctamente.");
    }

    /**
    * Recorta campos de texto antes de validarlos para evitar espacios accidentales.
    *
    * @param  Request  $request  Solicitud cuyos campos se normalizan.
    * @return void
    */
    private function normalizar(Request $request): void
    {
        $request->merge([
            'nombre' => trim((string) $request->input('nombre')),
            'modelo_educativo' => trim((string) $request->input('modelo_educativo')),
            'programa_educativo' => trim((string) $request->input('programa_educativo')),
        ]);
    }

    /**
    * Obtiene las reglas de validación para los campos de la asignatura.
    *
    * @return array Reglas de validación de los campos de la asignatura.
    */    
    private function reglas(): array
    {
        $modelo = request()->input('modelo_educativo');

        return [
            'modelo_educativo' => ['required', Rule::in(array_keys(Asignatura::modelosEducativos()))],
            'nombre' => ['required', 'string', 'max:120'],
            'programa_educativo' => ['required', Rule::in(Asignatura::programasEducativos(is_string($modelo) ? $modelo : null))],
            'cuatrimestre' => ['required', 'integer', 'between:1,10'],
        ];
    }

    /** Adapta los nombres recibidos del formulario a los atributos persistidos. */
    private function atributos(array $datos): array
    {
        return [
            'modelo_educativo' => $datos['modelo_educativo'],
            'nombre_asignatura' => $datos['nombre'],
            'programa_educativo' => $datos['programa_educativo'],
            'cuatrimestre' => (int) $datos['cuatrimestre'],
        ];
    }

    /** Construye una clave única con abreviatura de programa e ID de asignatura. */
    private function generarClave(int $id, string $modelo, string $programa, ?int $ignorarId = null): string
    {
        $abreviatura = str_replace([' ', '/'], '', (string) Asignatura::abreviaturaDePrograma($modelo, $programa));
        $base = $abreviatura.'-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        $clave = $base;
        $sufijo = 1;

        while (Asignatura::query()
            ->where('clave_asignatura', $clave)
            ->when($ignorarId !== null, fn ($consulta) => $consulta->where('id_asignatura', '!=', $ignorarId))
            ->exists()) {
            $clave = $base.'-'.$sufijo++;
        }

        return $clave;
    }

    /** Prepara los filtros de la página para conservarlos después de editar o eliminar. */
    private function filtrosDeRetorno(Request $request): array
    {
        return array_filter([
            'q' => $request->input('q'),
            'modelo_educativo' => $request->input('filtro_modelo'),
            'programa' => $request->input('filtro_programa'),
            'cuatrimestre' => $request->input('filtro_cuatrimestre'),
            'page' => $request->input('page'),
        ], fn ($valor) => filled($valor));
    }

    /** Mensajes de validación mostrados junto a los campos del formulario. */
    private function mensajes(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'max' => 'No puede exceder :max caracteres.',
            'cuatrimestre.integer' => 'Selecciona un cuatrimestre válido.',
            'cuatrimestre.between' => 'El cuatrimestre debe estar entre 1 y 10.',
        ];
    }
}
