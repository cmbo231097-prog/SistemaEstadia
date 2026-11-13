<?php

namespace App\Jobs;

use App\Models\CorpusDocumento;
use App\Services\EvaluacionIaService;
use App\Services\GeminiEvaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EvaluarDocumentoConGemini implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 360;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $idCorpus) {}

    public function uniqueId(): string
    {
        return (string) $this->idCorpus;
    }

    public function handle(GeminiEvaluationService $gemini, EvaluacionIaService $guardado): void
    {
        $documento = CorpusDocumento::with(['evidencia.criteriosRubrica'])->findOrFail($this->idCorpus);

        if (! $documento->estaSanitizado() || ! $documento->ruta_archivo_sanitizado) {
            throw new RuntimeException('El documento debe estar sanitizado antes de evaluarse.');
        }

        $criterios = $documento->evidencia->criteriosRubrica->where('evaluable_ia', true)->values()->all();
        if ($criterios === []) {
            throw new RuntimeException('La evidencia no tiene criterios de rúbrica evaluables por la IA.');
        }

        $ruta = Storage::path($documento->ruta_archivo_sanitizado);
        $inicio = microtime(true);
        $resultado = $gemini->evaluar(
            $ruta,
            $documento->evidencia->titulo,
            $criterios
        );
        $duracion = round(microtime(true) - $inicio, 2);

        $guardado->guardar($documento, $resultado, $duracion);
    }
}
