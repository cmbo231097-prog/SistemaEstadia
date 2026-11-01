<?php

namespace App\Http\Controllers;

use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\User;
use App\Services\PdfSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CorpusController extends Controller
{
    public const MAX_POR_LOTE = 10;
    private const MAX_KB = 20480;

    public function __construct(private readonly PdfSanitizer $sanitizador)
    {
    }

    public function index(Request $request)
    {
        $grupos = $this->gruposDisponibles();
        $asignaturas = $grupos->pluck('asignatura')->filter()->unique('id_asignatura')->values();

        $asignatura = $asignaturas->firstWhere('id_asignatura', (int) $request->query('asignatura')) ?? $asignaturas->first();
        $gruposAsignatura = $grupos->where('id_asignatura', $asignatura?->id_asignatura)->values();
        $grupo = $gruposAsignatura->firstWhere('id_grupo', (int) $request->query('grupo')) ?? $gruposAsignatura->first();

        $evidencias = $grupo
            ? $this->evidenciasVisibles()->whereHas('grupos', fn ($q) => $q->where('grupos.id_grupo', $grupo->id_grupo))->orderBy('titulo')->get()
            : collect();
        $evidencia = $evidencias->firstWhere('id_evidencia', (int) $request->query('evidencia')) ?? $evidencias->first();

        $equipos = $grupo ? Equipo::where('id_grupo', $grupo->id_grupo)->withCount('integrantes')->orderBy('id_equipo')->get() : collect();

        $documentos = collect();
        if ($evidencia && $equipos->isNotEmpty()) {
            $documentos = CorpusDocumento::with('equipo')
                ->where('id_evidencia', $evidencia->id_evidencia)
                ->whereIn('id_equipo', $equipos->pluck('id_equipo'))
                ->orderBy('id_corpus')
                ->get()
                ->map(function (CorpusDocumento $doc) {
                    $doc->paginas = $this->sanitizador->contarPaginas(Storage::path($doc->ruta_archivo_original));

                    return $doc;
                });
        }

        return view('corpus.index', [
            'user' => Auth::user(),
            'roleName' => User::roles()[Auth::user()->role] ?? '',
            'asignaturas' => $asignaturas,
            'grupos' => $gruposAsignatura,
            'evidencias' => $evidencias,
            'asignatura' => $asignatura,
            'grupo' => $grupo,
            'evidencia' => $evidencia,
            'equipos' => $equipos,
            'equiposJs' => $equipos->map(fn ($e) => ['id' => $e->id_equipo, 'nombre' => $e->nombre_equipo])->values(),
            'documentos' => $documentos,
            'maxLote' => self::MAX_POR_LOTE,
            'alumnosGrupo' => $equipos->sum('integrantes_count'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'id_evidencia' => ['required', 'integer'],
            'id_grupo' => ['required', 'integer'],
            'archivos' => ['required', 'array', 'min:1', 'max:'.self::MAX_POR_LOTE],
            'archivos.*' => ['file', 'extensions:pdf', 'mimetypes:application/pdf', 'max:'.self::MAX_KB],
            'equipos' => ['required', 'array'],
            'equipos.*' => ['required', 'integer'],
        ], [
            'archivos.required' => 'Selecciona al menos un archivo PDF.',
            'archivos.max' => 'Máximo '.self::MAX_POR_LOTE.' PDFs por lote.',
            'archivos.*.extensions' => 'Formato no admitido: solo se aceptan archivos PDF.',
            'archivos.*.mimetypes' => 'Formato no admitido: solo se aceptan archivos PDF.',
            'archivos.*.max' => 'Cada PDF debe pesar máximo 20 MB.',
            'archivos.*.uploaded' => 'No se pudo subir uno de los archivos (revisa su tamaño).',
            'equipos.*.required' => 'Asigna un equipo a cada PDF.',
        ]);

        $grupo = $this->gruposDisponibles()->firstWhere('id_grupo', (int) $request->input('id_grupo'));
        abort_unless($grupo, 403, 'No puedes cargar corpus en este grupo.');

        $evidencia = $this->evidenciasVisibles()
            ->whereKey($request->integer('id_evidencia'))
            ->whereHas('grupos', fn ($q) => $q->where('grupos.id_grupo', $grupo->id_grupo))
            ->first();
        abort_unless($evidencia, 403, 'La evidencia no corresponde a este grupo.');

        $archivos = array_values($request->file('archivos'));
        $equiposIds = array_values($request->input('equipos'));
        $validos = Equipo::where('id_grupo', $grupo->id_grupo)->pluck('id_equipo')->all();

        $errores = [];
        if (count($equiposIds) !== count($archivos)) {
            $errores['equipos'] = 'Asigna un equipo a cada PDF.';
        } elseif (array_diff($equiposIds, $validos)) {
            $errores['equipos'] = 'Uno de los equipos no pertenece al grupo seleccionado.';
        } elseif (count(array_unique($equiposIds)) !== count($equiposIds)) {
            $errores['equipos'] = 'Cada equipo solo puede tener un PDF por evidencia.';
        } else {
            $existentes = CorpusDocumento::where('id_evidencia', $evidencia->id_evidencia)->whereIn('id_equipo', $equiposIds)->exists();
            if ($existentes) {
                $errores['equipos'] = 'Alguno de los equipos ya tiene un PDF cargado para esta evidencia. Elimínalo antes de volver a subirlo.';
            }
        }
        foreach ($archivos as $archivo) {
            if (! str_starts_with((string) file_get_contents($archivo->getRealPath(), false, null, 0, 5), '%PDF-')) {
                $errores['archivos'] = 'Formato no admitido: solo se aceptan archivos PDF.';
            }
        }
        if ($errores) {
            return $this->volver($grupo, $evidencia)->withErrors($errores)->withInput();
        }

        $sanitizados = 0;
        $fallidos = [];
        foreach ($archivos as $i => $archivo) {
            $ruta = Storage::putFileAs(
                "corpus/{$evidencia->id_evidencia}/originales",
                $archivo,
                Str::uuid().'.pdf'
            );

            $doc = CorpusDocumento::create([
                'id_evidencia' => $evidencia->id_evidencia,
                'id_equipo' => $equiposIds[$i],
                'nombre_archivo_original' => Str::limit($archivo->getClientOriginalName(), 255, ''),
                'ruta_archivo_original' => $ruta,
                'estado_sanitizacion' => CorpusDocumento::PENDIENTE,
            ]);

            $this->sanitizar($doc) ? $sanitizados++ : $fallidos[] = $doc->nombre_archivo_original;
        }

        $respuesta = $this->volver($grupo, $evidencia);
        if ($sanitizados > 0) {
            $respuesta->with('status', "Portadas suprimidas correctamente en {$sanitizados} PDF(s). El lote quedó listo para la evaluación con IA.");
        }
        if ($fallidos) {
            $respuesta->with('error', 'No se pudo sanitizar: '.implode(', ', $fallidos).'. Revisa que el PDF tenga al menos 2 páginas y reintenta con «Sanitizar».');
        }

        return $respuesta;
    }

    public function sanitizarLote(Request $request): RedirectResponse
    {
        $datos = $request->validate(['id_evidencia' => ['required', 'integer'], 'id_grupo' => ['required', 'integer']]);

        $grupo = $this->gruposDisponibles()->firstWhere('id_grupo', (int) $datos['id_grupo']);
        abort_unless($grupo, 403);
        $evidencia = $this->evidenciasVisibles()->findOrFail($datos['id_evidencia']);

        $pendientes = CorpusDocumento::where('id_evidencia', $evidencia->id_evidencia)
            ->whereIn('id_equipo', Equipo::where('id_grupo', $grupo->id_grupo)->pluck('id_equipo'))
            ->where('estado_sanitizacion', '!=', CorpusDocumento::SANITIZADO)
            ->get();

        $ok = $pendientes->filter(fn ($doc) => $this->sanitizar($doc))->count();
        $fallos = $pendientes->count() - $ok;

        $respuesta = $this->volver($grupo, $evidencia);
        if ($ok > 0) {
            $respuesta->with('status', "Portadas suprimidas correctamente en {$ok} PDF(s).");
        }
        if ($fallos > 0) {
            $respuesta->with('error', "{$fallos} PDF(s) no pudieron sanitizarse. Verifica que tengan al menos 2 páginas.");
        }

        return $respuesta;
    }

    public function sanitizarUno(CorpusDocumento $documento): RedirectResponse
    {
        $this->autorizar($documento);

        $ok = $this->sanitizar($documento);
        $respuesta = $this->volverDeDocumento($documento);

        return $ok
            ? $respuesta->with('status', "Portada suprimida correctamente en {$documento->nombre_archivo_original}.")
            : $respuesta->with('error', "No se pudo sanitizar {$documento->nombre_archivo_original}. Verifica que tenga al menos 2 páginas.");
    }

    public function ver(CorpusDocumento $documento): BinaryFileResponse
    {
        $this->autorizar($documento);

        $ruta = $documento->estaSanitizado() && $documento->ruta_archivo_sanitizado
            ? $documento->ruta_archivo_sanitizado
            : $documento->ruta_archivo_original;
        abort_unless(Storage::exists($ruta), 404, 'El archivo ya no está disponible.');

        return response()->file(Storage::path($ruta), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::ascii($documento->nombre_archivo_original).'"',
        ]);
    }

    public function destroy(CorpusDocumento $documento): RedirectResponse
    {
        $this->autorizar($documento);

        $respuesta = $this->volverDeDocumento($documento);
        $nombre = $documento->nombre_archivo_original;

        Storage::delete(array_filter([$documento->ruta_archivo_original, $documento->ruta_archivo_sanitizado]));
        $documento->delete();

        return $respuesta->with('status', "El PDF {$nombre} se eliminó correctamente.");
    }

    private function sanitizar(CorpusDocumento $doc): bool
    {
        $destino = "corpus/{$doc->id_evidencia}/sanitizados/".Str::uuid().'.pdf';

        try {
            Storage::makeDirectory(dirname($destino));
            $this->sanitizador->sanitizar(Storage::path($doc->ruta_archivo_original), Storage::path($destino));
        } catch (Throwable $e) {
            report($e);
            Storage::delete($destino);
            $doc->update(['estado_sanitizacion' => CorpusDocumento::ERROR, 'ruta_archivo_sanitizado' => null]);

            return false;
        }

        if ($doc->ruta_archivo_sanitizado && $doc->ruta_archivo_sanitizado !== $destino) {
            Storage::delete($doc->ruta_archivo_sanitizado);
        }
        $doc->update(['estado_sanitizacion' => CorpusDocumento::SANITIZADO, 'ruta_archivo_sanitizado' => $destino]);

        return true;
    }

    private function esLider(): bool
    {
        return Auth::user()?->role === User::ROLE_LIDER;
    }

    private function evidenciasVisibles()
    {
        return Evidencia::query()->when(! $this->esLider(), fn ($q) => $q->where('id_docente', Auth::id()));
    }

    private function gruposDisponibles()
    {
        return Grupo::query()
            ->with('asignatura')
            ->when(! $this->esLider(), fn ($q) => $q->where('id_docente', Auth::id()))
            ->orderBy('clave_grupo')
            ->get();
    }

    private function autorizar(CorpusDocumento $doc): void
    {
        $evidencia = $doc->evidencia;
        abort_unless($evidencia && ($this->esLider() || (int) $evidencia->id_docente === (int) Auth::id()), 403, 'Solo puedes gestionar tu propio corpus.');
    }

    private function volver(Grupo $grupo, Evidencia $evidencia): RedirectResponse
    {
        return redirect()->route('corpus.index', [
            'asignatura' => $grupo->id_asignatura,
            'grupo' => $grupo->id_grupo,
            'evidencia' => $evidencia->id_evidencia,
        ]);
    }

    private function volverDeDocumento(CorpusDocumento $doc): RedirectResponse
    {
        $grupo = $doc->equipo?->grupo;

        return $grupo
            ? $this->volver($grupo, $doc->evidencia)
            : redirect()->route('corpus.index');
    }
}
