<?php

namespace App\Http\Controllers;

use App\Models\CriterioRubrica;
use App\Models\Evidencia;
use App\Models\User;
use App\Services\RubricaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RubricaController extends Controller
{
    public function __construct(private readonly RubricaService $rubricas) {}

    public function index(Evidencia $evidencia)
    {
        $this->autorizar($evidencia);
        $evidencia->load('criteriosRubrica');

        return view('evidencias.rubrica', [
            'user' => Auth::user(),
            'roleName' => User::roles()[Auth::user()->role] ?? '',
            'evidencia' => $evidencia,
            'criterios' => $evidencia->criteriosRubrica,
            'bloqueada' => $this->rubricas->estaBloqueada($evidencia),
            'puntajeTotal' => CriterioRubrica::PUNTAJE_TOTAL,
            'maxCriterios' => CriterioRubrica::MAX_CRITERIOS,
        ]);
    }

    public function update(Request $request, Evidencia $evidencia): RedirectResponse
    {
        $this->autorizar($evidencia);
        $this->asegurarEditable($evidencia);

        $this->rubricas->guardar($evidencia, $this->rubricas->validar($request->all()));

        return redirect()->route('evidencias.rubrica', $evidencia)
            ->with('status', 'La rúbrica se guardó correctamente.');
    }

    private function autorizar(Evidencia $evidencia): void
    {
        abort_unless(
            Auth::user()?->role === User::ROLE_LIDER
                || (int) $evidencia->id_docente === (int) Auth::id(),
            403,
            'Solo puedes configurar la rúbrica de tus propias evidencias.'
        );
    }

    private function asegurarEditable(Evidencia $evidencia): void
    {
        abort_if(
            $this->rubricas->estaBloqueada($evidencia),
            409,
            'La rúbrica no se puede cambiar después de generar evaluaciones; conserva el historial de resultados.'
        );
    }
}
