<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Evita que el navegador muestre pantallas guardadas (al usar «Atrás» o pestañas abiertas) con listados ya desactualizados.
class SinCacheNavegador
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Las descargas y los PDF se sirven como archivo y conservan su manejo de caché.
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
