<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/** FN.2 Gestión de usuarios: alta, consulta, modificación y baja de cuentas. */
class UsuarioController extends Controller
{
    private const POR_PAGINA = 5;
    private const DOMINIO = '@upemor.edu.mx';
    // 3 letras mayúsculas + "O" (otoño) + año de registro de 2 dígitos posterior a 2020 + 4 dígitos.
    private const FORMATO_MATRICULA = '/^[A-Z]{3}O(2[1-9]|[3-9][0-9])[0-9]{4}$/';

    public function index(Request $request)
    {
        $busqueda = trim((string) $request->query('q', ''));
        $rol = (string) $request->query('rol', '');
        $estado = (string) $request->query('estado', User::ESTATUS_ACTIVO);

        $consulta = User::query()->with('rol')->orderBy('apellidos')->orderBy('nombre');

        // Cada palabra debe coincidir con alguno de los campos (matrícula, nombre, apellidos o correo).
        foreach (preg_split('/\s+/', $busqueda, -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $consulta->where(function ($q) use ($termino) {
                $q->where('matricula', 'like', "%{$termino}%")
                    ->orWhere('nombre', 'like', "%{$termino}%")
                    ->orWhere('apellidos', 'like', "%{$termino}%")
                    ->orWhere('correo_institucional', 'like', "%{$termino}%");
            });
        }

        if (array_key_exists($rol, User::roles())) {
            $consulta->where('id_rol', Role::idPorClave($rol) ?? 0);
        }

        if (in_array($estado, [User::ESTATUS_ACTIVO, User::ESTATUS_INACTIVO], true)) {
            $consulta->where('estatus', $estado);
        }

        // Si el formulario falló, se reabre el modal en el modo (alta/edición) en que estaba.
        $modoPrevio = $request->old('_modo');
        $usuarioPrevio = $modoPrevio === 'editar' ? User::find($request->old('_id')) : null;

        return view('admin.usuarios.index', [
            'user' => Auth::user(),
            'roleName' => User::roles()[User::ROLE_LIDER],
            'usuarios' => $consulta->paginate(self::POR_PAGINA)->withQueryString(),
            'filtros' => ['q' => $busqueda, 'rol' => $rol, 'estado' => $estado],
            'roles' => User::roles(),
            'reabrir' => $modoPrevio ? [
                'modo' => $modoPrevio,
                'action' => $usuarioPrevio ? route('admin.usuarios.update', $usuarioPrevio) : route('admin.usuarios.store'),
                'esSelf' => (bool) $usuarioPrevio?->is(Auth::user()),
                'esLider' => $usuarioPrevio?->role === User::ROLE_LIDER,
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizar($request);
        $datos = $request->validate($this->reglas(), $this->mensajes());

        User::create($this->atributos($datos) + [
            'password_hash' => $datos['password'],
            'estatus' => User::ESTATUS_ACTIVO,
        ]);

        return redirect()->route('admin.usuarios.index')
            ->with('status', "Usuario {$datos['nombre']} {$datos['apellidos']} registrado correctamente.");
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $this->normalizar($request);

        // La propia cuenta del Líder no puede cambiarse de rol ni desactivarse.
        if ($usuario->is(Auth::user())) {
            $request->merge(['rol' => $usuario->role, 'estatus' => User::ESTATUS_ACTIVO]);
        }

        $datos = $request->validate($this->reglas($usuario), $this->mensajes());

        $cambios = $this->atributos($datos) + ['estatus' => $datos['estatus']];

        if (! empty($datos['password'])) {
            $cambios['password_hash'] = $datos['password'];
        }

        $usuario->update($cambios);

        return redirect()->route('admin.usuarios.index', $this->filtrosDeRetorno($request))
            ->with('status', "Usuario {$usuario->nombre_completo} actualizado correctamente.");
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        $volver = redirect()->route('admin.usuarios.index', $this->filtrosDeRetorno($request));

        if ($usuario->is(Auth::user())) {
            return $volver->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($usuario->role === User::ROLE_LIDER) {
            return $volver->with('error', 'Las cuentas de Líder de Proyecto no se pueden eliminar desde este módulo.');
        }

        $nombre = $usuario->nombre_completo;

        try {
            $usuario->delete();
        } catch (QueryException) {
            // Registros vinculados (evidencias, calificaciones): se conserva la integridad referencial con una baja lógica.
            $usuario->update(['estatus' => User::ESTATUS_INACTIVO]);

            return $volver->with('status', "{$nombre} tiene registros vinculados; se dio de baja (inactivo) para conservar la integridad referencial.");
        }

        return $volver->with('status', "Usuario {$nombre} eliminado correctamente.");
    }

    /** Conserva búsqueda, filtros y página al volver al listado; en los formularios el filtro de rol viaja como filtro_rol. */
    private function filtrosDeRetorno(Request $request): array
    {
        return array_filter([
            'q' => $request->input('q'),
            'rol' => $request->input('filtro_rol'),
            'estado' => $request->input('estado'),
            'page' => $request->input('page'),
        ], fn ($valor) => filled($valor));
    }

    private function normalizar(Request $request): void
    {
        $request->merge([
            'matricula' => strtoupper(trim((string) $request->input('matricula'))),
            'correo_institucional' => strtolower(trim((string) $request->input('correo_institucional'))),
            'nombre' => trim((string) $request->input('nombre')),
            'apellidos' => trim((string) $request->input('apellidos')),
        ]);
    }

    private function reglas(?User $usuario = null): array
    {
        $id = $usuario?->getKey();
        // Solo un Líder de Proyecto puede asignar el rol de Líder.
        $roles = array_keys(User::roles());
        if (Auth::user()?->role !== User::ROLE_LIDER) {
            $roles = array_diff($roles, [User::ROLE_LIDER]);
        }

        $reglas = [
            'matricula' => [
                'required', 'string', 'max:20',
                Rule::unique('usuarios', 'matricula')->ignore($id, 'id_usuario'),
                // El formato se exige en altas y cuando la matrícula cambia (las cuentas previas conservan la suya).
                function (string $atributo, mixed $valor, \Closure $fallo) use ($usuario) {
                    $sinCambio = $usuario && $valor === $usuario->matricula;
                    if (! $sinCambio && ! preg_match(self::FORMATO_MATRICULA, (string) $valor)) {
                        $fallo('Formato inválido: 3 letras mayúsculas, una O, año de registro (21 en adelante) y 4 dígitos. Ej. ITIO230001.');
                    }
                },
            ],
            'nombre' => ['required', 'string', 'max:60'],
            'apellidos' => ['required', 'string', 'max:80'],
            'correo_institucional' => [
                'required', 'email', 'max:120',
                Rule::unique('usuarios', 'correo_institucional')->ignore($id, 'id_usuario'),
                // El dominio institucional se exige en altas y cuando el correo cambia.
                function (string $atributo, mixed $valor, \Closure $fallo) use ($usuario) {
                    $sinCambio = $usuario && $valor === $usuario->correo_institucional;
                    if (! $sinCambio && ! str_ends_with((string) $valor, self::DOMINIO)) {
                        $fallo('El correo debe ser institucional ('.self::DOMINIO.').');
                    }
                },
            ],
            'rol' => ['required', Rule::in($roles)],
            'password' => $usuario
                ? ['nullable', 'string', 'min:8', 'confirmed']
                : ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($usuario) {
            $reglas['estatus'] = ['required', Rule::in([User::ESTATUS_ACTIVO, User::ESTATUS_INACTIVO])];
        }

        return $reglas;
    }

    private function atributos(array $datos): array
    {
        return [
            'matricula' => $datos['matricula'],
            'nombre' => $datos['nombre'],
            'apellidos' => $datos['apellidos'],
            'correo_institucional' => $datos['correo_institucional'],
            'id_rol' => Role::idOCrearPorClave($datos['rol']),
        ];
    }

    private function mensajes(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'max' => 'No puede exceder :max caracteres.',
            'email' => 'Ingresa un correo válido.',
            'unique' => 'Ya existe un usuario con este dato.',
            'rol.in' => 'Selecciona un rol válido.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
