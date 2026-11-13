@extends('layouts.panel')

@section('title', 'Rúbrica de Evaluación')

@section('styles')
    @include('partials.dashboard-styles')

    .act-btn { background:#fff; border:1px solid var(--border); font-size:12px; font-weight:700; padding:7px 12px; border-radius:6px; color:var(--blue); cursor:pointer; }
    .act-btn:hover { background:var(--info-bg); }
    .act-btn:focus-visible { outline:3px solid #93b4ff; outline-offset:1px; }
    .act-del { color:var(--bad-fg); border-color:#f3b4b4; }
    .act-del:hover { background:var(--bad-bg); }
    .rubric-btns { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
    .rubric-card { padding:20px; margin-bottom:16px; }
    .rubric-table { width:100%; border-collapse:collapse; }
    .rubric-table th, .rubric-table td { padding:10px; text-align:left; vertical-align:top; border-bottom:1px solid var(--border); }
    .rubric-table input, .rubric-table textarea, .rubric-table select { width:100%; padding:9px; border:1px solid var(--border); border-radius:7px; font:inherit; }
    .rubric-table textarea { min-height:68px; resize:vertical; }
    .rubric-actions { display:flex; flex-wrap:wrap; justify-content:space-between; gap:12px; margin-top:16px; }
    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }
    .rubric-note { color:var(--muted); font-size:12px; }
    @media (max-width:760px) {
        .rubric-table, .rubric-table tbody, .rubric-table tr, .rubric-table td { display:block; width:100%; }
        .rubric-table thead { display:none; }
        .rubric-table tr { padding:12px 0; border-bottom:1px solid var(--border); }
        .rubric-table td { border:0; padding:5px 0; }
    }
@endsection

@section('sidebar')
    @include(Auth::user()->role === 'lider' ? 'partials.lider-sidebar' : 'partials.profesor-sidebar')
@endsection

@section('content')
    <h1 class="page-title">Rúbrica de evaluación</h1>
    <p class="page-sub">{{ $evidencia->titulo }} — La suma de los criterios debe ser {{ number_format($puntajeTotal, 0) }} puntos.</p>

    @if (session('status'))
        <div class="alert alert-ok" role="status" data-autocierre>{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-err" role="alert">{{ $errors->first() }}</div>
    @endif
    @if ($bloqueada)
        <div class="alert alert-ok" role="status">La rúbrica está bloqueada porque ya existen evaluaciones. Se conserva sin cambios para proteger el historial.</div>
    @endif

    @php
        $criteriosFormulario = old('criterios', $criterios->isEmpty() ? [
            ['nombre_criterio' => 'Comprensión del tema', 'descripcion_criterio' => 'Exactitud y dominio de los conceptos principales.', 'puntaje_maximo' => '20.00', 'evaluable_ia' => true],
            ['nombre_criterio' => 'Desarrollo y metodología', 'descripcion_criterio' => 'Claridad, secuencia y aplicación del método solicitado.', 'puntaje_maximo' => '30.00', 'evaluable_ia' => true],
            ['nombre_criterio' => 'Análisis y resultados', 'descripcion_criterio' => 'Interpretación, evidencia y validez de los resultados.', 'puntaje_maximo' => '20.00', 'evaluable_ia' => true],
            ['nombre_criterio' => 'Conclusiones', 'descripcion_criterio' => 'Conclusiones relacionadas con los objetivos y resultados.', 'puntaje_maximo' => '20.00', 'evaluable_ia' => true],
            ['nombre_criterio' => 'Presentación y referencias', 'descripcion_criterio' => 'Organización, redacción y referencias pertinentes.', 'puntaje_maximo' => '10.00', 'evaluable_ia' => true],
        ] : $criterios->map(fn ($criterio) => [
            'nombre_criterio' => $criterio->nombre_criterio,
            'descripcion_criterio' => $criterio->descripcion_criterio,
            'puntaje_maximo' => $criterio->puntaje_maximo,
            'evaluable_ia' => $criterio->evaluable_ia,
        ])->all());
    @endphp

    <form method="POST" action="{{ route('evidencias.rubrica.update', $evidencia) }}" class="panel rubric-card">
        @csrf
        @method('PUT')
        <table class="rubric-table">
            <thead>
                <tr>
                    <th scope="col">Criterio</th>
                    <th scope="col">Puntaje máximo</th>
                    <th scope="col">Evaluable desde el producto</th>
                    @unless ($bloqueada)<th scope="col">Acción</th>@endunless
                </tr>
            </thead>
            <tbody id="criteriosRubrica">
                @foreach ($criteriosFormulario as $indice => $criterio)
                    <tr>
                        <td>
                            <input name="criterios[{{ $indice }}][nombre_criterio]" value="{{ $criterio['nombre_criterio'] }}" maxlength="100" aria-label="Nombre del criterio" required @disabled($bloqueada)>
                            @if (filled($criterio['descripcion_criterio'] ?? null))
                                <input type="hidden" name="criterios[{{ $indice }}][descripcion_criterio]" value="{{ $criterio['descripcion_criterio'] }}" @disabled($bloqueada)>
                            @endif
                        </td>
                        <td><input type="number" name="criterios[{{ $indice }}][puntaje_maximo]" value="{{ $criterio['puntaje_maximo'] }}" min="0.01" max="{{ $puntajeTotal }}" step="0.01" aria-label="Puntaje máximo" required @disabled($bloqueada)></td>
                        <td>
                            <select name="criterios[{{ $indice }}][evaluable_ia]" aria-label="Evaluable desde el producto" @disabled($bloqueada)>
                                <option value="1" @selected($criterio['evaluable_ia'] ?? true)>Sí</option>
                                <option value="0" @selected(! ($criterio['evaluable_ia'] ?? true))>No</option>
                            </select>
                        </td>
                        @unless ($bloqueada)<td><button type="button" class="act-btn act-del quitar-criterio">Quitar</button></td>@endunless
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="rubric-note">Se recomienda evaluar criterios observables y verificables en el PDF anonimizado. La evaluación cuantitativa será la suma de los puntajes por criterio (sobre {{ $puntajeTotal }}). Evaluable desde el producto: «Sí» significa que la IA evalúa el criterio; «No» significa que la IA no lo evalúa (lo califica el docente).</p>
        @unless ($bloqueada)
            <div class="rubric-actions">
                <button type="button" class="btn-outline" id="agregarCriterio">＋ Agregar criterio</button>
                <div class="rubric-btns">
                    <a href="{{ route('evidencias.index') }}" class="btn-outline">Volver</a>
                    <button type="submit" class="btn btn-navy">Guardar rúbrica</button>
                </div>
            </div>
        @else
            <div class="rubric-actions"><a href="{{ route('evidencias.index') }}" class="btn-outline">Volver</a></div>
        @endunless
    </form>

    @unless ($bloqueada)
        <template id="criterioPlantilla">
            <tr>
                <td><input data-campo="nombre_criterio" maxlength="100" aria-label="Nombre del criterio" required></td>
                <td><input data-campo="puntaje_maximo" type="number" min="0.01" max="{{ $puntajeTotal }}" step="0.01" aria-label="Puntaje máximo" required></td>
                <td>
                    <select data-campo="evaluable_ia" aria-label="Evaluable desde el producto">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </td>
                <td><button type="button" class="act-btn act-del quitar-criterio">Quitar</button></td>
            </tr>
        </template>
        <script>
            (() => {
                const tbody = document.getElementById('criteriosRubrica');
                const plantilla = document.getElementById('criterioPlantilla');
                let siguiente = tbody.querySelectorAll('tr').length;

                document.getElementById('agregarCriterio').addEventListener('click', () => {
                    if (tbody.querySelectorAll('tr').length >= {{ $maxCriterios }}) return;
                    const fila = plantilla.content.cloneNode(true);
                    fila.querySelectorAll('[data-campo]').forEach((campo) => {
                        campo.name = `criterios[${siguiente}][${campo.dataset.campo}]`;
                    });
                    tbody.appendChild(fila);
                    siguiente++;
                });

                tbody.addEventListener('click', (evento) => {
                    if (evento.target.matches('.quitar-criterio')) evento.target.closest('tr').remove();
                });
            })();
        </script>
    @endunless
@endsection
