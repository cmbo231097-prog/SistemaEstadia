<?php

namespace App\Services;

use App\Jobs\ProcesarLoteEvaluacionIa;
use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\LoteEvaluacionIa;
use App\Models\Notificacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

// Envía a la IA los PDFs pendientes de una evidencia uno por uno y avisa al usuario cuando termina el lote.
class LoteEvaluacionIaService
{
    public function __construct(private readonly EvaluacionIaService $evaluaciones) {}

    /** Documentos sanitizados, con archivo disponible y que aún no tienen evaluación de IA. */
    public function pendientes(Evidencia $evidencia, Grupo $grupo): Collection
    {
        return CorpusDocumento::with('equipo')
            ->where('id_evidencia', $evidencia->id_evidencia)
            ->whereIn('id_equipo', Equipo::where('id_grupo', $grupo->id_grupo)->pluck('id_equipo'))
            ->where('estado_sanitizacion', CorpusDocumento::SANITIZADO)
            ->whereNotNull('ruta_archivo_sanitizado')
            ->doesntHave('evaluacionesIa')
            ->orderBy('id_corpus')
            ->get()
            ->filter(fn (CorpusDocumento $doc) => Storage::exists($doc->ruta_archivo_sanitizado))
            ->values();
    }

    public function activo(int $idEvidencia, int $idGrupo): ?LoteEvaluacionIa
    {
        return LoteEvaluacionIa::where('id_evidencia', $idEvidencia)->where('id_grupo', $idGrupo)
            ->where('estado', '!=', LoteEvaluacionIa::TERMINADO)->latest('id_lote')->first();
    }

    public function crear(int $idUsuario, Evidencia $evidencia, Grupo $grupo, Collection $documentos): LoteEvaluacionIa
    {
        return LoteEvaluacionIa::create([
            'id_usuario' => $idUsuario,
            'id_evidencia' => $evidencia->id_evidencia,
            'id_grupo' => $grupo->id_grupo,
            'estado' => LoteEvaluacionIa::EN_COLA,
            'total' => $documentos->count(),
            'documentos' => $documentos->map(fn (CorpusDocumento $doc) => [
                'id_corpus' => $doc->id_corpus,
                'equipo' => $doc->equipo?->nombre_equipo ?? 'Documento '.$doc->id_corpus,
                'estado' => 'pendiente',
                'mensaje' => null,
            ])->all(),
        ]);
    }

    /** Pone en la cola el primer documento del lote y garantiza que haya un procesador atendiéndola. */
    public function iniciar(LoteEvaluacionIa $lote): void
    {
        ProcesarLoteEvaluacionIa::dispatch($lote->id_lote);
        $this->iniciarProcesador();
    }

    /**
     * Evalúa el siguiente documento pendiente del lote y registra el resultado.
     *
     * @return bool true si quedan documentos por evaluar.
     */
    public function procesarSiguiente(int $idLote): bool
    {
        $lote = LoteEvaluacionIa::find($idLote);
        if (! $lote || ! $lote->estaActivo()) {
            return false;
        }

        $documentos = $lote->documentos;
        $indice = $this->siguiente($documentos);
        if ($indice === null) {
            $this->finalizar($lote);

            return false;
        }

        $documentos[$indice]['estado'] = 'procesando';
        $lote->update(['estado' => LoteEvaluacionIa::PROCESANDO, 'documentos' => $documentos]);

        try {
            $documento = CorpusDocumento::findOrFail($documentos[$indice]['id_corpus']);
            $this->evaluaciones->evaluarDocumento($documento);
            $documentos[$indice]['estado'] = 'ok';
        } catch (Throwable $e) {
            report($e);
            $documentos[$indice]['estado'] = 'error';
            $documentos[$indice]['mensaje'] = $this->evaluaciones->mensajeDeError($e);
        }

        return $this->registrar($lote, $documentos);
    }

    /** Marca como fallido el documento en curso (el proceso se interrumpió) y deja listo el siguiente. */
    public function registrarInterrupcion(int $idLote, string $mensaje): bool
    {
        $lote = LoteEvaluacionIa::find($idLote);
        if (! $lote || ! $lote->estaActivo()) {
            return false;
        }

        $documentos = $lote->documentos;
        foreach ($documentos as $i => $doc) {
            if ($doc['estado'] === 'procesando') {
                $documentos[$i]['estado'] = 'error';
                $documentos[$i]['mensaje'] = $mensaje;
            }
        }

        return $this->registrar($lote, $documentos);
    }

    /** Lanza un procesador de la cola en segundo plano; sin él los jobs esperarían a un `queue:work` manual. */
    public function iniciarProcesador(): void
    {
        if (config('queue.default') !== 'database' || ! function_exists('exec')) {
            return;
        }

        $comando = sprintf(
            'nohup %s %s queue:work --stop-when-empty --tries=1 --timeout=0 >> %s 2>&1 &',
            escapeshellarg((string) config('services.php_binary')),
            escapeshellarg(base_path('artisan')),
            escapeshellarg(storage_path('logs/cola-ia.log'))
        );

        try {
            exec($comando);
        } catch (Throwable $e) {
            Log::warning('No se pudo iniciar el procesador de la cola: '.$e->getMessage());
        }
    }

    /** @param  array<int, array<string, mixed>>  $documentos */
    private function registrar(LoteEvaluacionIa $lote, array $documentos): bool
    {
        $conteo = array_count_values(array_column($documentos, 'estado'));
        $lote->update([
            'documentos' => $documentos,
            'exitosos' => $conteo['ok'] ?? 0,
            'fallidos' => $conteo['error'] ?? 0,
        ]);

        if ($this->siguiente($documentos) !== null) {
            return true;
        }
        $this->finalizar($lote);

        return false;
    }

    /** @param  array<int, array<string, mixed>>  $documentos */
    private function siguiente(array $documentos): ?int
    {
        foreach ($documentos as $i => $doc) {
            if ($doc['estado'] === 'pendiente') {
                return $i;
            }
        }

        return null;
    }

    private function finalizar(LoteEvaluacionIa $lote): void
    {
        $lote->update(['estado' => LoteEvaluacionIa::TERMINADO, 'finalizado_en' => now()]);
        $lote->load(['evidencia', 'grupo']);

        $cuando = trim(($lote->evidencia?->titulo ?? 'la evidencia').' · grupo '.($lote->grupo?->clave_grupo ?? $lote->id_grupo));
        $resumen = $lote->fallidos === 0
            ? "Se evaluaron con IA los {$lote->total} PDFs del lote de {$cuando}."
            : "Terminó el lote de {$cuando}: {$lote->exitosos} de {$lote->total} PDFs evaluados y {$lote->fallidos} con error. Revisa el detalle en «Evaluación con IA».";

        Notificacion::avisar(
            $lote->id_usuario,
            $lote->fallidos === 0 ? 'Lote evaluado por la IA' : 'Lote evaluado con errores',
            $resumen,
            Notificacion::TIPO_LOTE_IA
        );
    }
}
