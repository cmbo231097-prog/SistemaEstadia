<?php

namespace App\Http\Controllers;

use App\Models\RespaldoBd;
use App\Models\User;
use App\Services\DatabaseBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RespaldoController extends Controller
{
    private const POR_PAGINA = 4;

    public function __construct(private readonly DatabaseBackup $respaldos)
    {
    }

    public function index()
    {
        $usuario = Auth::user();
        $historial = RespaldoBd::orderByDesc('fecha_respaldo')->orderByDesc('id_respaldo')->paginate(self::POR_PAGINA);
        $historial->getCollection()->transform(function (RespaldoBd $r) {
            $r->disponible = Storage::exists($this->ruta($r->nombre_archivo));

            return $r;
        });

        $ultimo = RespaldoBd::orderByDesc('fecha_respaldo')->orderByDesc('id_respaldo')->first();
        $gestor = $this->respaldos->gestor();

        return view('respaldos.index', [
            'user' => $usuario,
            'roleName' => User::roles()[$usuario->role] ?? '',
            'historial' => $historial,
            'gestor' => $gestor,
            'tamanoBd' => $this->respaldos->tamanoMb(),
            'totalTablas' => count($this->respaldos->tablas()),
            'ultimo' => $ultimo,
            'totalCopias' => RespaldoBd::count(),
            'almacenamientoMb' => (float) RespaldoBd::sum('tamano_mb'),
            'maxKb' => config('backup.max_upload_kb'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $respaldo = $this->generar($request->boolean('comprimir'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('respaldos.index')
            ->with('status', "Respaldo {$respaldo->nombre_archivo} generado correctamente. Ya puedes descargarlo desde el historial.");
    }

    public function restaurarDesdeHistorial(RespaldoBd $respaldo): RedirectResponse
    {
        $ruta = $this->ruta($respaldo->nombre_archivo);
        if (! Storage::exists($ruta)) {
            return back()->with('error', 'El archivo de este respaldo ya no está disponible en el servidor.');
        }

        return $this->restaurar(Storage::path($ruta), $respaldo->nombre_archivo);
    }

    public function restaurarDesdeArchivo(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'extensions:sql,gz', 'max:'.config('backup.max_upload_kb')],
        ], [
            'archivo.required' => 'Selecciona un archivo .sql para restaurar.',
            'archivo.extensions' => 'Formato no admitido: solo se aceptan archivos .sql o .sql.gz.',
            'archivo.max' => 'El archivo supera el tamaño máximo permitido.',
            'archivo.uploaded' => 'No se pudo cargar el archivo (revisa su tamaño).',
        ]);

        $archivo = $request->file('archivo');
        $nombre = $archivo->getClientOriginalName();
        $temporal = Storage::putFileAs(config('backup.directory').'/tmp', $archivo, uniqid('restore_', true).(str_ends_with(strtolower($nombre), '.gz') ? '.sql.gz' : '.sql'));

        try {
            return $this->restaurar(Storage::path($temporal), $nombre);
        } finally {
            Storage::delete($temporal);
        }
    }

    public function descargar(RespaldoBd $respaldo): StreamedResponse
    {
        $ruta = $this->ruta($respaldo->nombre_archivo);
        abort_unless(Storage::exists($ruta), 404, 'El archivo de respaldo ya no está disponible.');

        return Storage::download($ruta, $respaldo->nombre_archivo);
    }

    public function destroy(RespaldoBd $respaldo): RedirectResponse
    {
        Storage::delete($this->ruta($respaldo->nombre_archivo));
        $nombre = $respaldo->nombre_archivo;
        $respaldo->delete();

        return redirect()->route('respaldos.index')->with('status', "El respaldo {$nombre} se eliminó correctamente.");
    }

    private function restaurar(string $rutaAbsoluta, string $nombreMostrado): RedirectResponse
    {
        if ($error = $this->respaldos->validarArchivo($rutaAbsoluta)) {
            return back()->with('error', "Restauración cancelada: {$error} No se modificó la base de datos.");
        }

        try {
            $previo = $this->generar(false, '_previo_restauracion');
        } catch (RuntimeException $e) {
            return back()->with('error', 'Restauración cancelada: no se pudo crear la copia de seguridad previa. '.$e->getMessage());
        }

        try {
            $this->respaldos->cargar($rutaAbsoluta);
        } catch (Throwable $e) {
            report($e);
            try {
                $this->respaldos->cargar(Storage::path($this->ruta($previo->nombre_archivo)));
                $detalle = 'Se revirtió al estado anterior con la copia previa.';
            } catch (Throwable $e2) {
                report($e2);
                $detalle = "No se pudo revertir automáticamente; restaura manualmente {$previo->nombre_archivo}.";
            }

            return back()->with('error', "La restauración falló y se canceló. {$detalle}");
        }

        return redirect()->route('respaldos.index')
            ->with('status', "Base de datos restaurada correctamente desde {$nombreMostrado}. Se guardó una copia previa ({$previo->nombre_archivo}).");
    }

    private function generar(bool $comprimir, string $sufijo = ''): RespaldoBd
    {
        $directorio = config('backup.directory');
        Storage::makeDirectory($directorio);
        $carpeta = Storage::path($directorio);

        if (! is_writable($carpeta)) {
            throw new RuntimeException('Sin privilegios de escritura en el almacenamiento de respaldos.');
        }
        $necesario = max(50 * 1048576, (int) ($this->respaldos->tamanoMb() * 2 * 1048576));
        if ($this->respaldos->espacioLibreBytes($carpeta) < $necesario) {
            throw new RuntimeException('Espacio insuficiente en disco para generar el respaldo.');
        }

        $base = 'backup_upemor_'.now()->format('Y_m_d_Hi').$sufijo;
        $extension = $comprimir ? '.sql.gz' : '.sql';
        $nombre = $base.$extension;
        for ($i = 1; Storage::exists("{$directorio}/{$nombre}") || RespaldoBd::where('nombre_archivo', $nombre)->exists(); $i++) {
            $nombre = "{$base}_{$i}{$extension}";
        }

        $ruta = "{$directorio}/{$nombre}";
        try {
            $this->respaldos->volcar(Storage::path($ruta), $comprimir);
        } catch (Throwable $e) {
            report($e);
            Storage::delete($ruta);
            throw new RuntimeException('No se pudo generar el respaldo: espacio insuficiente o sin privilegios en el servidor de base de datos.');
        }

        return RespaldoBd::create([
            'nombre_archivo' => $nombre,
            'tamano_mb' => max(0.01, round(Storage::size($ruta) / 1048576, 2)),
            'alcance_tablas' => 'Completo ('.count($this->respaldos->tablas()).' tablas)',
            'id_usuario_creador' => Auth::id(),
            'fecha_respaldo' => now(),
        ]);
    }

    private function ruta(string $nombre): string
    {
        return config('backup.directory').'/'.basename($nombre);
    }
}
