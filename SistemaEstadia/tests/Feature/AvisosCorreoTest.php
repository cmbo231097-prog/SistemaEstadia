<?php

namespace Tests\Feature;

use App\Models\DispositivoUsuario;
use App\Models\Notificacion;
use App\Models\Role;
use App\Models\SolicitudProfesor;
use App\Models\User;
use App\Notifications\InicioSesionNuevoDispositivo;
use App\Notifications\PerfilModificado;
use App\Notifications\SolicitudCambioProfesor;
use App\Services\AvisosCorreoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AvisosCorreoTest extends TestCase
{
    use RefreshDatabase;

    private int $consecutivo = 0;

    private function usuario(string $rol, array $extra = []): User
    {
        $n = ++$this->consecutivo;
        $m = 'AVI'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);

        return User::create($extra + [
            'matricula' => $m,
            'nombre' => 'Nombre'.$n,
            'apellidos' => 'Apellido',
            'correo_institucional' => strtolower($m).'@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave($rol),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    private function iniciarSesion(User $usuario, array $cookies = [], string $agente = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0 Safari/537.36')
    {
        // Cada inicio de sesión simula un navegador con solo las cookies indicadas.
        $this->defaultCookies = [];

        return $this->withCookies($cookies)->withHeader('User-Agent', $agente)->post(route('login.submit'), [
            'email' => $usuario->correo_institucional,
            'password' => 'password123',
        ]);
    }

    // ---- Solicitud para ser profesor -------------------------------------------------

    public function test_el_estudiante_solicita_ser_profesor_y_solo_el_lider_recibe_el_correo(): void
    {
        Notification::fake();
        $lider = $this->usuario(User::ROLE_LIDER);
        $otroLider = $this->usuario(User::ROLE_LIDER);
        $profesor = $this->usuario(User::ROLE_PROFESOR);
        $estudiante = $this->usuario(User::ROLE_ESTUDIANTE);

        $this->actingAs($estudiante)->get(route('dashboard'))->assertOk()
            ->assertSee('Solicitar cambio a Profesor')
            ->assertSee(route('solicitud-profesor.store'), false);

        $this->post(route('solicitud-profesor.store'))->assertRedirect()->assertSessionHas('status');

        $this->assertSame(SolicitudProfesor::PENDIENTE, SolicitudProfesor::sole()->estado);
        Notification::assertSentTo($lider, SolicitudCambioProfesor::class, function ($n, $canales) use ($lider, $estudiante) {
            $texto = $n->toMail($lider)->render();

            return $canales === ['mail']
                && str_contains($texto, $estudiante->matricula)
                && str_contains($texto, $estudiante->correo_institucional);
        });
        Notification::assertNotSentTo([$otroLider, $profesor, $estudiante], SolicitudCambioProfesor::class);
        Notification::assertCount(1);

        $aviso = Notificacion::where('id_usuario', $lider->id_usuario)->sole();
        $this->assertSame('solicitud_profesor', $aviso->tipo_evento);
        $this->assertSame('ambos', $aviso->canal);
    }

    public function test_no_se_puede_duplicar_una_solicitud_pendiente_y_el_boton_lo_indica(): void
    {
        Notification::fake();
        $this->usuario(User::ROLE_LIDER);
        $estudiante = $this->usuario(User::ROLE_ESTUDIANTE);

        $this->actingAs($estudiante)->post(route('solicitud-profesor.store'));
        $this->post(route('solicitud-profesor.store'))->assertSessionHas('error');

        $this->assertSame(1, SolicitudProfesor::count());
        Notification::assertCount(1);
        $this->get(route('dashboard'))->assertSee('Solicitud pendiente')->assertSee('está pendiente de revisión');
    }

    public function test_solo_los_estudiantes_pueden_solicitar_el_cambio(): void
    {
        $this->post(route('solicitud-profesor.store'))->assertRedirect(route('login'));
        $this->actingAs($this->usuario(User::ROLE_PROFESOR))->post(route('solicitud-profesor.store'))->assertForbidden();
        $this->actingAs($this->usuario(User::ROLE_LIDER))->post(route('solicitud-profesor.store'))->assertForbidden();
        $this->assertSame(0, SolicitudProfesor::count());
    }

    public function test_el_destinatario_puede_configurarse_y_sin_lider_la_solicitud_se_registra(): void
    {
        Notification::fake();
        $primero = $this->usuario(User::ROLE_LIDER);
        $elegido = $this->usuario(User::ROLE_LIDER);
        $estudiante = $this->usuario(User::ROLE_ESTUDIANTE);

        config()->set('services.solicitudes_profesor.correo', strtoupper($elegido->correo_institucional));
        $this->actingAs($estudiante)->post(route('solicitud-profesor.store'));
        Notification::assertSentTo($elegido, SolicitudCambioProfesor::class);
        Notification::assertNotSentTo($primero, SolicitudCambioProfesor::class);

        // Un Líder inactivo no recibe el aviso.
        SolicitudProfesor::query()->delete();
        $elegido->update(['estatus' => User::ESTATUS_INACTIVO]);
        $this->post(route('solicitud-profesor.store'));
        Notification::assertSentTo($primero, SolicitudCambioProfesor::class);

        // Sin ningún Líder activo la solicitud se guarda igual y se informa.
        SolicitudProfesor::query()->delete();
        $primero->update(['estatus' => User::ESTATUS_INACTIVO]);
        $this->post(route('solicitud-profesor.store'))->assertSessionHas('status', fn ($m) => str_contains($m, 'Aún no hay un Líder activo'));
        $this->assertSame(1, SolicitudProfesor::count());
    }

    public function test_al_cambiar_el_rol_la_solicitud_queda_atendida_y_el_usuario_recibe_el_aviso(): void
    {
        Notification::fake();
        $lider = $this->usuario(User::ROLE_LIDER);
        $estudiante = $this->usuario(User::ROLE_ESTUDIANTE);
        SolicitudProfesor::create(['id_usuario' => $estudiante->id_usuario, 'estado' => SolicitudProfesor::PENDIENTE]);

        $this->actingAs($lider)->put(route('admin.usuarios.update', $estudiante), [
            'matricula' => $estudiante->matricula, 'nombre' => $estudiante->nombre, 'apellidos' => $estudiante->apellidos,
            'correo_institucional' => $estudiante->correo_institucional, 'rol' => User::ROLE_PROFESOR, 'estatus' => User::ESTATUS_ACTIVO,
        ])->assertSessionHasNoErrors();

        $this->assertSame(SolicitudProfesor::ATENDIDA, SolicitudProfesor::sole()->estado);
        Notification::assertSentTo($estudiante, PerfilModificado::class, fn ($n) => str_contains($n->toMail($estudiante)->render(), 'Rol'));
    }

    // ---- Perfil modificado -----------------------------------------------------------

    public function test_modificar_un_perfil_avisa_al_titular_con_los_datos_cambiados_sin_mostrar_valores(): void
    {
        Notification::fake();
        $lider = $this->usuario(User::ROLE_LIDER);
        $usuario = $this->usuario(User::ROLE_PROFESOR);
        $correoAnterior = $usuario->correo_institucional;

        $this->actingAs($lider)->put(route('admin.usuarios.update', $usuario), [
            'matricula' => $usuario->matricula, 'nombre' => 'Nuevo Nombre', 'apellidos' => $usuario->apellidos,
            'correo_institucional' => 'nuevo.correo@upemor.edu.mx', 'rol' => User::ROLE_PROFESOR, 'estatus' => User::ESTATUS_ACTIVO,
            'password' => 'ClaveSecreta123', 'password_confirmation' => 'ClaveSecreta123',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($usuario, PerfilModificado::class, function ($n) use ($usuario) {
            $texto = $n->toMail($usuario)->render();

            return str_contains($texto, 'Nombre') && str_contains($texto, 'Correo institucional') && str_contains($texto, 'Contraseña')
                && ! str_contains($texto, 'ClaveSecreta123') && ! str_contains($texto, 'Apellidos');
        });
        // Al cambiar el correo también se avisa a la dirección anterior.
        Notification::assertSentOnDemand(PerfilModificado::class, fn ($n, $canales, $anonimo) => $anonimo->routes['mail'] === $correoAnterior);
        $this->assertSame('perfil_modificado', Notificacion::where('id_usuario', $usuario->id_usuario)->sole()->tipo_evento);
    }

    public function test_sin_cambios_no_se_envia_aviso_y_cambiar_el_estatus_si_lo_envia(): void
    {
        Notification::fake();
        $lider = $this->usuario(User::ROLE_LIDER);
        $usuario = $this->usuario(User::ROLE_ESTUDIANTE);

        $this->actingAs($lider)->put(route('admin.usuarios.update', $usuario), [
            'matricula' => $usuario->matricula, 'nombre' => $usuario->nombre, 'apellidos' => $usuario->apellidos,
            'correo_institucional' => $usuario->correo_institucional, 'rol' => User::ROLE_ESTUDIANTE, 'estatus' => User::ESTATUS_ACTIVO,
        ])->assertSessionHasNoErrors();
        Notification::assertNothingSent();

        $this->patch(route('admin.usuarios.estatus', $usuario));
        Notification::assertSentTo($usuario, PerfilModificado::class, fn ($n) => str_contains($n->toMail($usuario)->render(), 'inactiva'));
    }

    // ---- Inicio de sesión desde un dispositivo nuevo ------------------------------------

    public function test_el_primer_acceso_solo_registra_el_dispositivo_sin_enviar_correo(): void
    {
        Notification::fake();
        $usuario = $this->usuario(User::ROLE_ESTUDIANTE);

        $respuesta = $this->iniciarSesion($usuario)->assertRedirect(route('dashboard'));

        Notification::assertNothingSent();
        $this->assertSame(1, DispositivoUsuario::count());
        $this->assertSame('Chrome en macOS', DispositivoUsuario::first()->descripcion);
        $this->assertNotEmpty($respuesta->getCookie(AvisosCorreoService::COOKIE_DISPOSITIVO));
    }

    public function test_el_mismo_navegador_no_genera_aviso_pero_uno_nuevo_si_para_todos_los_roles(): void
    {
        Notification::fake();
        // Se hacen más de 5 inicios de sesión seguidos; el límite de intentos no es lo que se prueba aquí.
        $this->withoutMiddleware(ThrottleRequests::class);

        foreach ([User::ROLE_ESTUDIANTE, User::ROLE_PROFESOR, User::ROLE_LIDER] as $rol) {
            $usuario = $this->usuario($rol);
            $primero = $this->iniciarSesion($usuario);
            $cookie = $primero->getCookie(AvisosCorreoService::COOKIE_DISPOSITIVO)->getValue();
            $this->post(route('logout'));

            // Mismo navegador: sin aviso.
            $this->iniciarSesion($usuario, [AvisosCorreoService::COOKIE_DISPOSITIVO => $cookie]);
            Notification::assertNotSentTo($usuario, InicioSesionNuevoDispositivo::class);
            $this->post(route('logout'));

            // Navegador distinto: aviso.
            $this->iniciarSesion($usuario, [], 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0');
            Notification::assertSentTo($usuario, InicioSesionNuevoDispositivo::class, function ($n) use ($usuario) {
                return str_contains($n->toMail($usuario)->render(), 'Firefox en Windows');
            });
            $this->assertSame(2, DispositivoUsuario::where('id_usuario', $usuario->id_usuario)->count());
            $this->assertSame('inicio_sesion_nuevo_dispositivo', Notificacion::where('id_usuario', $usuario->id_usuario)->sole()->tipo_evento);
            $this->post(route('logout'));
        }
    }

    public function test_un_navegador_conocido_de_otra_cuenta_cuenta_como_nuevo_para_esta(): void
    {
        Notification::fake();
        $uno = $this->usuario(User::ROLE_PROFESOR);
        $dos = $this->usuario(User::ROLE_PROFESOR);

        $cookie = $this->iniciarSesion($uno)->getCookie(AvisosCorreoService::COOKIE_DISPOSITIVO)->getValue();
        $this->post(route('logout'));
        // Primer acceso de la segunda cuenta desde el mismo navegador: solo se registra.
        $this->iniciarSesion($dos, [AvisosCorreoService::COOKIE_DISPOSITIVO => $cookie]);
        $this->post(route('logout'));
        Notification::assertNothingSent();

        $this->iniciarSesion($dos, [], 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0 Mobile Safari/537.36');
        Notification::assertSentTo($dos, InicioSesionNuevoDispositivo::class);
        Notification::assertNotSentTo($uno, InicioSesionNuevoDispositivo::class);
    }

    public function test_un_fallo_al_enviar_el_correo_no_impide_iniciar_sesion(): void
    {
        $usuario = $this->usuario(User::ROLE_ESTUDIANTE);
        $this->iniciarSesion($usuario);
        $this->post(route('logout'));

        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', '127.0.0.1');
        config()->set('mail.mailers.smtp.port', 1);

        $this->iniciarSesion($usuario, [], 'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0')
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_describe_el_dispositivo_a_partir_del_user_agent(): void
    {
        $servicio = new AvisosCorreoService;

        $this->assertSame('Edge en Windows', $servicio->describirDispositivo('Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36 Edg/120'));
        $this->assertSame('Safari en iOS', $servicio->describirDispositivo('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1'));
        $this->assertSame('Navegador desconocido en sistema desconocido', $servicio->describirDispositivo(''));
    }
}
