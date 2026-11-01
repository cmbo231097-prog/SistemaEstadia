<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EquipoController extends Controller
{
    private const POR_PAGINA = 5;

    public function index(Request $request)
    {
        $usuario = Auth::user();
        $busqueda = trim((string) $request->query('q', ''));
        $asignatura = (string) $request->query('asignatura', '');
        $grupo = (string) $request->query('grupo', '');

        $consulta = $this->equiposVisibles()
            ->with(['grupo.asignatura', 'integrantes'])
            ->orderBy('id_grupo')
            ->orderBy('nombre_equipo');

        if ($busqueda !== '') {
            $consulta->where(function ($q) use ($busqueda) {
                $q->where('nombre_equipo', 'like', "%{$busqueda}%")
                    ->orWhereHas('integrantes', fn ($i) => $i->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('apellidos', 'like', "%{$busqueda}%")
                        ->orWhere('matricula', 'like', "%{$busqueda}%"));
            });
        }

        if (ctype_digit($asignatura) && $asignatura !== '') {
            $consulta->whereHas('grupo', fn ($q) => $q->where('id_asignatura', (int) $asignatura));
        }

        if (ctype_digit($grupo) && $grupo !== '') {
            $consulta->where('id_grupo', (int) $grupo);
        }

        $grupos = $this->gruposDisponibles();
        $estudiantes = $this->estudiantes();
        $modoPrevio = $request->old('_modo');
        $equipoPrevio = $modoPrevio === 'editar' ? Equipo::find($request->old('_id')) : null;

        return view('equipos.index', [
            'user' => $usuario,
            'roleName' => User::roles()[$usuario->role] ?? '',
            'equipos' => $consulta->paginate(self::POR_PAGINA)->withQueryString(),
            'grupos' => $grupos,
            'asignaturas' => $grupos->pluck('asignatura')->filter()->unique('id_asignatura')->sortBy('nombre_asignatura')->values(),
            'filtros' => ['q' => $busqueda, 'asignatura' => $asignatura, 'grupo' => $grupo],
            'min' => Equipo::MIN_INTEGRANTES,
            'max' => Equipo::MAX_INTEGRANTES,
            'estudiantesJs' => $estudiantes->map(fn ($e) => [
                'id' => (string) $e->getKey(),
                'texto' => $e->nombre_completo.' ('.$e->matricula.')',
            ])->values(),
            'ocupadosJs' => $this->ocupadosPorGrupo($grupos),
            'reabrir' => $modoPrevio ? [
                'modo' => $modoPrevio,
                'action' => $equipoPrevio ? route('equipos.update', $equipoPrevio) : route('equipos.store'),
                'id' => $equipoPrevio?->getKey(),
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $equipo = DB::transaction(function () use ($datos) {
            $equipo = Equipo::create(['nombre_equipo' => $datos['nombre'], 'id_grupo' => $datos['id_grupo']]);
            $this->asignarIntegrantes($equipo, $datos);

            return $equipo;
        });

        return redirect()->route('equipos.index')
            ->with('status', "El equipo {$equipo->nombre_equipo} se registró con ".count($datos['integrantes']).' integrantes.');
    }

    public function update(Request $request, Equipo $equipo): RedirectResponse
    {
        $this->autorizar($equipo);
        $datos = $this->validar($request, $equipo);

        DB::transaction(function () use ($equipo, $datos) {
            $equipo->update(['nombre_equipo' => $datos['nombre'], 'id_grupo' => $datos['id_grupo']]);
            $this->asignarIntegrantes($equipo, $datos);
        });

        return redirect()->route('equipos.index', $this->filtrosDeRetorno($request))
            ->with('status', "El equipo {$equipo->nombre_equipo} se actualizó correctamente.");
    }

    public function destroy(Request $request, Equipo $equipo): RedirectResponse
    {
        $this->autorizar($equipo);
        $volver = redirect()->route('equipos.index', $this->filtrosDeRetorno($request));
        $nombre = $equipo->nombre_equipo;

        try {
            $equipo->delete();
        } catch (QueryException) {
            return $volver->with('error', "No se puede eliminar {$nombre} porque tiene entregas o evaluaciones vinculadas.");
        }

        return $volver->with('status', "El equipo {$nombre} se eliminó correctamente.");
    }

    private function esLider(): bool
    {
        return Auth::user()?->role === User::ROLE_LIDER;
    }

    private function equiposVisibles()
    {
        return Equipo::query()->when(! $this->esLider(), fn ($q) => $q->whereHas('grupo', fn ($g) => $g->where('id_docente', Auth::id())));
    }

    private function autorizar(Equipo $equipo): void
    {
        $equipo->loadMissing('grupo');
        abort_unless(
            $this->esLider() || (int) $equipo->grupo?->id_docente === (int) Auth::id(),
            403,
            'Solo puedes gestionar los equipos de tus grupos.'
        );
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

    private function estudiantes()
    {
        return User::query()
            ->where('id_rol', Role::idPorClave(User::ROLE_ESTUDIANTE) ?? 0)
            ->where('estatus', User::ESTATUS_ACTIVO)
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->get();
    }

    /** Por grupo: alumno => equipo que ya lo ocupa, para no ofrecerlo en otro equipo del mismo grupo. */
    private function ocupadosPorGrupo($grupos): array
    {
        $mapa = [];
        $filas = DB::table('equipo_estudiantes as ee')
            ->join('equipos as e', 'e.id_equipo', '=', 'ee.id_equipo')
            ->whereIn('e.id_grupo', $grupos->pluck('id_grupo'))
            ->get(['e.id_grupo', 'e.id_equipo', 'ee.id_estudiante']);

        foreach ($filas as $fila) {
            $mapa[$fila->id_grupo][$fila->id_estudiante] = $fila->id_equipo;
        }

        return $mapa;
    }

    private function validar(Request $request, ?Equipo $actual = null): array
    {
        $request->merge([
            'nombre' => trim((string) $request->input('nombre')),
            'integrantes' => array_values(array_filter((array) $request->input('integrantes', []), fn ($v) => filled($v))),
        ]);

        $elegibles = $this->estudiantes()->pluck('id_usuario')->all();

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'id_grupo' => ['required', 'integer', Rule::in($this->gruposDisponibles()->pluck('id_grupo')->all())],
            'integrantes' => ['required', 'array', 'min:'.Equipo::MIN_INTEGRANTES, 'max:'.Equipo::MAX_INTEGRANTES],
            'integrantes.*' => ['integer', 'distinct', Rule::in($elegibles)],
        ], [
            'required' => 'Este campo es obligatorio.',
            'max' => 'No puede exceder :max caracteres.',
            'id_grupo.in' => 'Selecciona un grupo válido.',
            'integrantes.required' => 'Agrega al menos '.Equipo::MIN_INTEGRANTES.' integrantes.',
            'integrantes.min' => 'Un equipo necesita al menos '.Equipo::MIN_INTEGRANTES.' integrantes.',
            'integrantes.max' => 'Un equipo admite como máximo '.Equipo::MAX_INTEGRANTES.' integrantes.',
            'integrantes.*.distinct' => 'No repitas al mismo alumno en el equipo.',
            'integrantes.*.in' => 'Selecciona alumnos activos de la lista.',
        ]);

        $duplicado = Equipo::query()
            ->where('id_grupo', $datos['id_grupo'])
            ->where('nombre_equipo', $datos['nombre'])
            ->when($actual, fn ($q) => $q->where('id_equipo', '!=', $actual->getKey()))
            ->exists();

        if ($duplicado) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un equipo con ese nombre en el grupo.']);
        }

        $ocupados = DB::table('equipo_estudiantes as ee')
            ->join('equipos as e', 'e.id_equipo', '=', 'ee.id_equipo')
            ->where('e.id_grupo', $datos['id_grupo'])
            ->whereIn('ee.id_estudiante', $datos['integrantes'])
            ->when($actual, fn ($q) => $q->where('e.id_equipo', '!=', $actual->getKey()))
            ->exists();

        if ($ocupados) {
            throw ValidationException::withMessages(['integrantes' => 'Uno o más alumnos ya pertenecen a otro equipo de este grupo.']);
        }

        return $datos;
    }

    private function asignarIntegrantes(Equipo $equipo, array $datos): void
    {
        $ids = array_map('intval', $datos['integrantes']);
        $equipo->integrantes()->sync($ids);

        // Los alumnos asignados quedan inscritos en el grupo escolar.
        foreach ($ids as $id) {
            DB::table('grupo_estudiantes')->insertOrIgnore([
                'id_grupo' => $equipo->id_grupo,
                'id_estudiante' => $id,
            ]);
        }
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
