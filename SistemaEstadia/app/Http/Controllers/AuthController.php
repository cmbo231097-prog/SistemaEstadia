<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
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
    private const REMEMBER_COOKIE = 'remembered_email';
    private const INSTITUTIONAL_DOMAIN = '@upemor.edu.mx';

    public function showLoginForm(Request $request)
    {
        return view('auth.login', [
            'rememberedEmail' => $request->cookie(self::REMEMBER_COOKIE),
        ]);
    }

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

        $request->session()->regenerate();

        // "Recordar mi usuario" solo guarda el correo; la sesión siempre expira por inactividad.
        if ($request->boolean('remember')) {
            Cookie::queue(self::REMEMBER_COOKIE, $credentials['email'], 60 * 24 * 30);
        } else {
            Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:60'],
            'apellidos' => ['required', 'string', 'max:80'],
            'matricula' => ['required', 'string', 'max:20', 'unique:usuarios,matricula'],
            'email' => ['required', 'email', 'max:120', 'ends_with:'.self::INSTITUTIONAL_DOMAIN, 'unique:usuarios,correo_institucional'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
            'terms' => ['accepted'],
        ], $this->passwordMessages());

        User::create([
            'matricula' => $data['matricula'],
            'nombre' => $data['nombres'],
            'apellidos' => $data['apellidos'],
            'correo_institucional' => $data['email'],
            'password_hash' => $data['password'],
            'id_rol' => Role::idOCrearPorClave(User::ROLE_ESTUDIANTE),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);

        return redirect()->route('login')->with('status', 'Cuenta creada correctamente. Ya puedes iniciar sesión.');
    }

    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

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

        if (! $user || ! $user->isActivo()) {
            return back()->withInput()->withErrors([
                'email' => 'Este correo no está registrado en el sistema.',
            ]);
        }

        try {
            $status = Password::sendResetLink(['correo_institucional' => $correo]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors([
                'email' => 'No fue posible enviar el correo en este momento. Inténtalo más tarde.',
            ]);
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors([
                'email' => 'Ya enviamos un enlace hace un momento. Espera un minuto para solicitar otro.',
            ]);
        }

        return redirect()->route('password.request')
            ->with('status', 'Enviamos un enlace de recuperación a '.$correo.'. Expira en 15 minutos; revisa también tu carpeta de Spam.')
            ->with('enviado', true);
    }

    public function showResetForm(Request $request, string $token)
    {
        $email = Str::lower(trim((string) $request->query('email')));
        $user = User::where('correo_institucional', $email)->first();

        if (! $user || ! Password::tokenExists($user, $token)) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'El enlace de recuperación no es válido o ha expirado. Solicita uno nuevo.',
            ]);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ], $this->passwordMessages());

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

    public function redirectToGoogle()
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->withErrors([
                'email' => 'El acceso con Google Institucional aún no está configurado.',
            ]);
        }

        return $this->googleDriver()->with(['hd' => 'upemor.edu.mx'])->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = $this->googleDriver()->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'No fue posible iniciar sesión con Google. Inténtalo de nuevo.',
            ]);
        }

        $email = Str::lower((string) $googleUser->getEmail());
        if (! str_ends_with($email, self::INSTITUTIONAL_DOMAIN)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Usa tu cuenta de Google institucional (@upemor.edu.mx).',
            ]);
        }

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

        if (! $user->isActivo()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta está inactiva. Contacta al administrador.',
            ]);
        }

        if (! $user->google_id) {
            $user->forceFill(['google_id' => $googleUser->getId()])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // El redirect se arma con el host actual; debe coincidir con el registrado en Google Cloud.
    private function googleDriver()
    {
        return Socialite::driver('google')->redirectUrl(route('auth.google.callback'));
    }

    private function passwordMessages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'email' => 'Ingresa un correo electrónico válido.',
            'max' => 'El valor ingresado es demasiado largo.',
            'unique' => 'Este dato ya está registrado en el sistema.',
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
