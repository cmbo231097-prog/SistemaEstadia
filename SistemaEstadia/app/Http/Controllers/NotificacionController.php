<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Avisos del sistema del usuario en sesión: la campana del encabezado los consulta periódicamente.
class NotificacionController extends Controller
{
    private const MAXIMO = 15;

    public function index(): JsonResponse
    {
        $propias = Notificacion::where('id_usuario', Auth::id());

        return response()->json([
            'no_leidas' => (clone $propias)->where('leido', false)->count(),
            'notificaciones' => (clone $propias)->orderByDesc('id_notificacion')->limit(self::MAXIMO)->get()->map(fn (Notificacion $n) => [
                'id' => $n->id_notificacion,
                'titulo' => $n->titulo,
                'mensaje' => $n->mensaje,
                'leido' => $n->leido,
                'tipo' => $n->tipo_evento,
                'fecha' => $n->fecha_envio?->format('d/m/Y H:i'),
                'url' => match (true) {
                    str_starts_with($n->tipo_evento, 'evaluacion_ia') => route('evaluacion-ia.index'),
                    $n->tipo_evento === Notificacion::TIPO_SOLICITUD_PROFESOR => route('admin.usuarios.index'),
                    default => null,
                },
            ])->all(),
        ]);
    }

    /** Marca como leídas todas las notificaciones del usuario, o solo una si se indica `id`. */
    public function leer(Request $request): JsonResponse
    {
        $datos = $request->validate(['id' => ['nullable', 'integer']]);

        Notificacion::where('id_usuario', Auth::id())
            ->when(isset($datos['id']), fn ($q) => $q->where('id_notificacion', $datos['id']))
            ->update(['leido' => true]);

        return response()->json(['ok' => true]);
    }
}
