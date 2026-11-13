<?php

namespace App\Http\Controllers;

use App\Models\SolicitudProfesor;
use App\Services\AvisosCorreoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

// El estudiante pide convertirse en profesor; el Líder recibe un correo y decide desde Gestión de Usuarios.
class SolicitudProfesorController extends Controller
{
    public function store(AvisosCorreoService $avisos): RedirectResponse
    {
        $usuario = Auth::user();

        if (SolicitudProfesor::pendienteDe($usuario->id_usuario)) {
            return back()->with('error', 'Ya tienes una solicitud pendiente. El Líder del proyecto la revisará.');
        }

        SolicitudProfesor::create(['id_usuario' => $usuario->id_usuario, 'estado' => SolicitudProfesor::PENDIENTE]);
        $lider = $avisos->solicitudProfesor($usuario);

        return back()->with('status', $lider
            ? 'Solicitud enviada. El Líder del proyecto recibió un correo y revisará tu petición.'
            : 'Solicitud registrada. Aún no hay un Líder activo que reciba el aviso; se atenderá cuando lo haya.');
    }
}
