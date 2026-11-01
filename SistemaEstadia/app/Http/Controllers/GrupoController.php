<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GrupoController extends Controller
{
    private const POR_PAGINA = 5;

    public function index(Request $request)
    {
        $busqueda = trim((string) $request->query('q', ''));
        $docente = (string) $request->query('docente', '');
        $asignatura = (string) $request->query('asignatura', '');

        $consulta = Grupo::query()
            ->with(['asignatura', 'docente', 'periodo'])
            ->orderBy('clave_grupo');

        if ($busqueda !== '') {
            $consulta->where(function ($q) use ($busqueda) {
                $q->where('clave_grupo', 'like', "%{$busqueda}%")
                    ->orWhereHas('asignatura', fn ($a) => $a->where('nombre_asignatura', 'like', "%{$busqueda}%")
                        ->orWhere('clave_asignatura', 'like', "%{$busqueda}%"))
                    ->orWhereHas('docente', fn ($d) => $d->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('apellidos', 'like', "%{$busqueda}%"));
            });
        }

        if (ctype_digit($docente) && $docente !== '') {
            $consulta->where('id_docente', (int) $docente);
        }

        if (ctype_digit($asignatura) && $asignatura !== '') {
            $consulta->where('id_asignatura', (int) $asignatura);
        }

        $modoPrevio = $request->old('_modo');
        $grupoPrevio = $modoPrevio === 'editar' ? Grupo::find($request->old('_id')) : null;

        return view('admin.grupos.index', [
            'user' => Auth::user(),
            'roleName' => 'Líder de proyecto',
            'grupos' => $consulta->paginate(self::POR_PAGINA)->withQueryString(),
            'docentes' => $this->docentes(),
            'asignaturas' => Asignatura::query()->orderBy('nombre_asignatura')->get(),
            'filtros' => ['q' => $busqueda, 'docente' => $docente, 'asignatura' => $asignatura],
            'periodos' => Periodo::query()->orderByDesc('activo')->orderByDesc('fecha_inicio')->get(),
            'periodoPorDefecto' => Periodo::query()->where('activo', 1)->orderByDesc('fecha_inicio')->value('id_periodo'),
            'reabrir' => $modoPrevio ? [
                'modo' => $modoPrevio,
                'action' => $grupoPrevio
                    ? route('admin.grupos.update', $grupoPrevio)
                    : route('admin.grupos.store'),
                'id' => $grupoPrevio?->getKey(),
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $grupo = Grupo::create($datos);

        return redirect()->route('admin.grupos.index')
            ->with('status', "El grupo {$grupo->clave_grupo} se registró correctamente.");
    }

    public function update(Request $request, Grupo $grupo): RedirectResponse
    {
        $grupo->update($this->validar($request, $grupo));

        return redirect()->route('admin.grupos.index', $this->filtrosDeRetorno($request))
            ->with('status', "El grupo {$grupo->clave_grupo} se actualizó correctamente.");
    }

    public function destroy(Request $request, Grupo $grupo): RedirectResponse
    {
        $volver = redirect()->route('admin.grupos.index', $this->filtrosDeRetorno($request));
        $nombre = $grupo->clave_grupo;

        try {
            $grupo->delete();
        } catch (QueryException) {
            return $volver->with('error', "No se puede eliminar el grupo {$nombre} porque tiene equipos o evidencias vinculados.");
        }

        return $volver->with('status', "El grupo {$nombre} se eliminó correctamente.");
    }

    private function validar(Request $request, ?Grupo $actual = null): array
    {
        $request->merge(['identificador' => strtoupper(trim((string) $request->input('identificador')))]);

        $datos = $request->validate([
            'identificador' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\- ]+$/'],
            'id_asignatura' => ['required', 'integer', Rule::exists('asignaturas', 'id_asignatura')],
            'id_docente' => ['required', 'integer', Rule::in($this->docentes()->pluck('id_usuario')->all())],
            'id_periodo' => ['required', 'integer', Rule::exists('periodos_escolares', 'id_periodo')],
        ], [
            'required' => 'Este campo es obligatorio.',
            'max' => 'No puede exceder :max caracteres.',
            'identificador.regex' => 'Usa solo letras, números, espacios o guiones (ej. 9A).',
            'id_asignatura.exists' => 'Selecciona una asignatura válida.',
            'id_docente.in' => 'Selecciona un docente titular activo.',
            'id_periodo.exists' => 'Selecciona un periodo escolar válido.',
        ]);

        $duplicado = Grupo::query()
            ->where('clave_grupo', $datos['identificador'])
            ->where('id_asignatura', $datos['id_asignatura'])
            ->where('id_periodo', $datos['id_periodo'])
            ->when($actual, fn ($q) => $q->where('id_grupo', '!=', $actual->getKey()))
            ->exists();

        if ($duplicado) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'identificador' => 'Ya existe este grupo para la misma asignatura y periodo.',
            ]);
        }

        return [
            'clave_grupo' => $datos['identificador'],
            'id_asignatura' => (int) $datos['id_asignatura'],
            'id_docente' => (int) $datos['id_docente'],
            'id_periodo' => (int) $datos['id_periodo'],
        ];
    }

    private function docentes()
    {
        $idRol = Role::idPorClave(User::ROLE_PROFESOR);

        return User::query()
            ->where('id_rol', $idRol ?? 0)
            ->where('estatus', User::ESTATUS_ACTIVO)
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->get();
    }

    private function filtrosDeRetorno(Request $request): array
    {
        return array_filter([
            'q' => $request->input('q'),
            'docente' => $request->input('filtro_docente'),
            'asignatura' => $request->input('filtro_asignatura'),
            'page' => $request->input('page'),
        ], fn ($valor) => filled($valor));
    }
}
