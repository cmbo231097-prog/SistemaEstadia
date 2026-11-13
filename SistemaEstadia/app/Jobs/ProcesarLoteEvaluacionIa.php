<?php

namespace App\Jobs;

use App\Services\LoteEvaluacionIaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

// Evalúa un solo documento del lote y, si quedan más, encola el siguiente: así se procesan de uno en uno.
class ProcesarLoteEvaluacionIa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    // Menor que `retry_after` de la cola (420 s) para que nunca se duplique la ejecución.
    public int $timeout = 400;

    public function __construct(public readonly int $idLote) {}

    public function handle(LoteEvaluacionIaService $lotes): void
    {
        if ($lotes->procesarSiguiente($this->idLote)) {
            self::dispatch($this->idLote);
        }
    }

    public function failed(Throwable $e): void
    {
        $lotes = app(LoteEvaluacionIaService::class);

        if ($lotes->registrarInterrupcion($this->idLote, 'El procesamiento de este documento se interrumpió. Evalúalo de nuevo de forma individual.')) {
            self::dispatch($this->idLote);
            $lotes->iniciarProcesador();
        }
    }
}
