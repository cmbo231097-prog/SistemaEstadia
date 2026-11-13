<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Lote de PDFs enviados a la IA; cada documento se evalúa de uno en uno y su avance queda en `documentos`.
class LoteEvaluacionIa extends Model
{
    public const EN_COLA = 'en_cola';

    public const PROCESANDO = 'procesando';

    public const TERMINADO = 'terminado';

    protected $table = 'lotes_evaluacion_ia';

    protected $primaryKey = 'id_lote';

    protected $fillable = ['id_usuario', 'id_evidencia', 'id_grupo', 'estado', 'total', 'exitosos', 'fallidos', 'documentos', 'finalizado_en'];

    protected function casts(): array
    {
        return ['documentos' => 'array', 'finalizado_en' => 'datetime'];
    }

    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class, 'id_evidencia', 'id_evidencia');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'id_grupo', 'id_grupo');
    }

    public function estaActivo(): bool
    {
        return $this->estado !== self::TERMINADO;
    }

    public function procesados(): int
    {
        return $this->exitosos + $this->fallidos;
    }
}
