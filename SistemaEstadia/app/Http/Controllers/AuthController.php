<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AvisosCorreoService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /** Cookie opcional para recordar el correo, sin persistir la sesión del usuario. */
    private const REMEMBER_COOKIE = 'remembered_email';

    /** Dominio permitido para las cuentas institucionales. */
    private const INSTITUTIONAL_DOMAIN = '@upemor.edu.mx';

    /** Muestra el formulario y precarga el correo guardado, si existe. */
    public function showLoginForm(Request $request)
    {
        return view('auth.login', [
            'rememberedEmail' => $request->cookie(self::REMEMBER_COOKIE),
        ]);
    }

    /** Valida las credenciales, inicia una sesión segura y guarda la preferencia del correo. */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Ingresa tu correo institucional.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        // El correo se normaliza y solo se autentican cuentas activas.
        $attempt = [
            'correo_institucional' => Str::lower(trim($credentials['email'])),
            'password' => $credentials['password'],
            'estatus' => User::ESTATUS_ACTIVO,
        ];

        if (! Auth::attempt($attempt)) {
            // Alerta genérica: no revela si falló el correo o la contraseña.
            throw ValidationException::withMessages([
                'email' => ['Credenciales incorrectas. Verifica tus datos e inténtalo de nuevo.'],
            ]);
        }

        // Renovar el ID de sesión evita reutilizar el identificador previo a la autenticación.
        $request->session()->regenerate();
        app(AvisosCorreoService::class)->inicioDeSesion(Auth::user(), $request);

        // "Recordar mi usuario" solo guarda el correo; la sesión siempre expira por inactividad.
        if ($request->boolean('remember')) {
            Cookie::queue(self::REMEMBER_COOKIE, $credentials['email'], 60 * 24 * 30);
        } else {
            Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE));
        }

        return redirect()->intended(route('dashboard'));
    }

    /** Presenta el formulario de alta de una cuenta de estudiante. */
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    /** Crea una cuenta activa con rol Estudiante después de validar los datos de registro. */
    public function register(Request $request)
    {
        // Se normaliza el correo antes de comprobar su dominio y unicidad.
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:60'],
            'apellidos' => ['required', 'string', 'max:80'],
            'matricula' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:120', 'ends_with:'.self::INSTITUTIONAL_DOMAIN],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
            'terms' => ['accepted'],
        ], $this->passwordMessages());

        // El duplicado se informa sin indicar qué dato coincide para no revelar qué matrículas o correos existen.
        $duplicado = $this->usuarioYaRegistrado($data['matricula'], $data['email']);

        if (! $duplicado) {
            try {
                // El modelo User se encarga de guardar la contraseña con el hash configurado.
                User::create([
                    'matricula' => $data['matricula'],
                    'nombre' => $data['nombres'],
                    'apellidos' => $data['apellidos'],
                    'correo_institucional' => $data['email'],
                    'password_hash' => $data['password'],
                    'id_rol' => Role::idOCrearPorClave(User::ROLE_ESTUDIANTE),
                    'estatus' => User::ESTATUS_ACTIVO,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Otra solicitud pudo registrar los mismos datos entre la comprobación y el guardado.
                $duplicado = true;
            }
        }

        if ($duplicado) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors([
                'registro' => 'Ya existe un usuario registrado con los datos proporcionados.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Cuenta creada correctamente. Ya puedes iniciar sesión.');
    }

    /** Muestra el formulario para solicitar un enlace de recuperación. */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /** Solicita el enlace de recuperación para una cuenta institucional activa. */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Ingresa tu correo institucional.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ]);

        $correo = Str::lower(trim($request->input('email')));
        $user = User::where('correo_institucional', $correo)->first();

        // La respuesta es idéntica exista o no la cuenta, para no revelar qué correos están registrados.
        // No se envían enlaces a cuentas inexistentes o inactivas.
        if ($user && $user->isActivo()) {
            try {
                // El broker de Laravel genera el token y controla su vigencia y frecuencia de envío.
                Password::sendResetLink(['correo_institucional' => $correo]);
            } catch (\Throwable $e) {
                // El fallo solo se registra: mostrarlo distinguiría las cuentas existentes de las que no existen.
                report($e);
            }
        }

        return redirect()->route('password.request')
            ->with('status', 'Si el correo está registrado, recibirás un enlace de recuperación. Expira en 15 minutos; revisa también tu carpeta de Spam.')
            ->with('enviado', true);
    }

    /** Comprueba el token antes de mostrar la pantalla para definir una nueva contraseña. */
    public function showResetForm(Request $request, string $token)
    {
        $email = Str::lower(trim((string) $request->query('email')));
        $user = User::where('correo_institucional', $email)->first();

        if (! $user || ! Password::tokenExists($user, $token)) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'El enlace de recuperación no es válido o ha expirado. Solicita uno nuevo.',
            ]);
        }

        // El formulario recibe el correo y token ya verificados para completar el restablecimiento.
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /** Actualiza la contraseña mediante el broker y notifica el evento de restablecimiento. */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ], $this->passwordMessages());

        // El broker valida el token y ejecuta el callback solo si el enlace sigue siendo válido.
        $status = Password::reset(
            [
                'correo_institucional' => Str::lower(trim($request->input('email'))),
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user, string $password) {
                $user->forceFill(['password_hash' => $password])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['El enlace de recuperación no es válido o ha expirado.'],
            ]);
        }

        return redirect()->route('login')->with('status', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }

    /** Inicia el flujo OAuth de Google si las credenciales del proveedor están configuradas. */
    public function redirectToGoogle()
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->withErrors([
                'email' => 'El acceso con Google Institucional aún no está configurado.',
            ]);
        }

        return $this->googleDriver()->with(['hd' => 'upemor.edu.mx'])->redirect();
    }

    /** Valida la identidad institucional recibida y asocia o crea la cuenta local correspondiente. */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = $this->googleDriver()->user();
        } catch (\Throwable $e) {
            // La respuesta pública es genérica; el detalle del fallo OAuth no se expone al usuario.
            return redirect()->route('login')->withErrors([
                'email' => 'No fue posible iniciar sesión con Google. Inténtalo de nuevo.',
            ]);
        }

        // El parámetro hd de Google orienta el inicio de sesión, pero el dominio también se valida aquí.
        $email = Str::lower((string) $googleUser->getEmail());
        if (! str_ends_with($email, self::INSTITUTIONAL_DOMAIN)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Usa tu cuenta de Google institucional (@upemor.edu.mx).',
            ]);
        }

        // Se busca primero por identificador de Google y luego por correo institucional ya registrado.
        $user = User::where('google_id', $googleUser->getId())->orWhere('correo_institucional', $email)->first();

        // Primer acceso con Google: se crea la cuenta con rol Estudiante.
        if (! $user) {
            $matricula = Str::upper(Str::before($email, '@'));
            $raw = $googleUser->user;

            if (strlen($matricula) > 20 || User::where('matricula', $matricula)->exists()) {
                return redirect()->route('login')->withErrors([
                    'email' => 'No fue posible crear tu cuenta automáticamente. Regístrate con el formulario.',
                ]);
            }

            $user = User::create([
                'matricula' => $matricula,
                'nombre' => Str::limit($raw['given_name'] ?? $googleUser->getName() ?? $matricula, 60, ''),
                'apellidos' => Str::limit($raw['family_name'] ?? '-', 80, ''),
                'correo_institucional' => $email,
                'google_id' => $googleUser->getId(),
                'id_rol' => Role::idOCrearPorClave(User::ROLE_ESTUDIANTE),
                'estatus' => User::ESTATUS_ACTIVO,
            ]);
        }

        // Las cuentas inactivas no pueden iniciar sesión aunque Google autentique correctamente al usuario.
        if (! $user->isActivo()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta está inactiva. Contacta al administrador.',
            ]);
        }

        // Vincula el identificador de Google a una cuenta existente que aún no tenía esa asociación.
        if (! $user->google_id) {
            $user->forceFill(['google_id' => $googleUser->getId()])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();
        app(AvisosCorreoService::class)->inicioDeSesion($user, $request);

        return redirect()->intended(route('dashboard'));
    }

    /** Cierra la autenticación y limpia los datos de sesión para evitar reutilización. */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Configura Socialite con la URL de retorno generada para el host actual.
     * Esta URL debe coincidir con la registrada en la configuración OAuth de Google.
     */
    private function googleDriver()
    {
        return Socialite::driver('google')->redirectUrl(route('auth.google.callback'));
    }

    private function usuarioYaRegistrado(string $matricula, string $correo): bool
    {
        return User::query()
            ->where('matricula', $matricula)
            ->orWhere('correo_institucional', $correo)
            ->exists();
    }

    /** Centraliza los mensajes en español usados por las reglas de validación de contraseñas y registro. */
    private function passwordMessages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'email' => 'Ingresa un correo electrónico válido.',
            'max' => 'El valor ingresado es demasiado largo.',
            'confirmed' => 'Las contraseñas no coinciden.',
            'accepted' => 'Debes aceptar los términos de uso y el Aviso de Privacidad.',
            'email.ends_with' => 'Usa tu correo institucional (@upemor.edu.mx).',
            'password.min' => 'La contraseña debe tener mínimo 8 caracteres.',
            'password.mixed' => 'La contraseña debe combinar mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir al menos un número.',
            'password.symbols' => 'La contraseña debe incluir al menos un símbolo.',
        ];
    }
}
