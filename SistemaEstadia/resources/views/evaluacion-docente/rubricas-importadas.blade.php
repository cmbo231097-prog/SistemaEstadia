@extends('layouts.panel')

@section('title', 'Rúbricas importadas')

@section('styles')
    @include('partials.dashboard-styles')

    .act-btn { background:#fff; border:1px solid var(--border); font-size:12px; font-weight:700; padding:7px 12px; border-radius:6px; color:var(--blue); cursor:pointer; }
    .act-btn:hover { background:var(--info-bg); }
    .act-btn:focus-visible { outline:3px solid #93b4ff; outline-offset:1px; }
    .act-del { color:var(--bad-fg); border-color:#f3b4b4; }
    .act-del:hover { background:var(--bad-bg); }
    .rubric-btns { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
    .cta { display:inline-block; text-decoration:none; text-align:center; max-width:none; }
    .page-footer { display:flex; flex-wrap:wrap; justify-content:space-between; gap:12px; margin-top:8px; }
    .rubric-card { padding:20px; margin-bottom:16px; }
    .rubric-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
    .rubric-head h2 { margin:0; font-size:15px; }
    .rubric-head small { color:var(--muted); font-size:12px; }
    .rubric-table { width:100%; border-collapse:collapse; }
    .rubric-table th, .rubric-table td { padding:8px; text-align:left; vertical-align:top; border-bottom:1px solid var(--border); font-size:12.5px; }
    .rubric-table input, .rubric-table textarea, .rubric-table select { width:100%; padding:8px; border:1px solid var(--border); border-radius:7px; font:inherit; }
    .rubric-table textarea { min-height:56px; resize:vertical; }
    .rubric-actions { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px; margin-top:14px; }
    .rubric-total { font-size:13px; font-weight:700; }
    .rubric-total.bad { color:var(--bad-fg); }
    .rubric-total.good { color:var(--ok-fg); }
    .rubric-note { color:var(--muted); font-size:12px; }
    .match-fields { display:flex; flex-wrap:wrap; gap:12px; align-items:end; }
    .match-fields label { display:grid; gap:5px; flex:1 1 220px; font-size:13px; font-weight:700; }
    .match-fields select { width:100%; padding:9px 44px 9px 9px; border:1px solid var(--border); border-radius:7px; }
    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }
    .alert-warn { background:var(--warn-bg); color:var(--warn-fg); border-color:#f1d28a; }
    .alert ul { margin:0; padding-left:18px; }
    .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .badge-ok { background:var(--ok-bg); color:var(--ok-fg); }
    .badge-warn { background:var(--warn-bg); color:var(--warn-fg); }
    @media (max-width:760px) {
        .rubric-table, .rubric-table tbody, .rubric-table tr, .rubric-table td { display:block; width:100%; }
        .rubric-table thead { display:none; }
        .rubric-table tr { padding:12px 0; border-bottom:1px solid var(--border); }
        .rubric-table td { border:0; padding:5px 0; }
    }
@endsection

@section('sidebar')
    @include($user->role === 'lider' ? 'partials.lider-sidebar' : 'partials.profesor-sidebar')
@endsection

@section('content')
    <h1 class="page-title">Rúbricas importadas</h1>
    <p class="page-sub">
        Archivo «{{ $archivo }}». Completa y guarda el formulario de cada evidencia: la suma debe ser exactamente {{ $puntajeTotal }} puntos.
    </p>

    @if (session('status'))
        <div class="alert alert-ok" role="status" data-autocierre>{{ session('status') }}</div>
    @endif
    @if (session('error') || $errors->any())
        <div class="alert alert-err" role="alert">{{ session('error') ?: $errors->first() }}</div>
    @endif

    @foreach ($pendientes as $pendiente)
        <form method="POST" action="{{ route('evaluacion-docente.rubricas.asignar') }}" class="panel rubric-card asignacion-form"
            data-asignatura="{{ $pendiente['asignatura'] }}">
            @csrf
            <input type="hidden" name="codigo" value="{{ $pendiente['grupo']['codigo'] }}">
            <h2>{{ $pendiente['grupo']['codigo'] }}</h2>
            <p class="rubric-note">No se pudo identificar una evidencia única para «{{ $pendiente['grupo']['asignatura'] }}». Elige la asignatura y la evidencia correspondientes.</p>
            <div class="match-fields">
                <label>Asignatura
                    <select name="asignatura" class="asignacion-asignatura" required>
                        <option value="">Asignatura sin identificar</option>
                        @foreach ($asignaturas as $asignatura)
                            <option value="{{ $asignatura->id_asignatura }}" @selected($asignatura->id_asignatura == $pendiente['asignatura'])>{{ $asignatura->nombre_asignatura }} ({{ $asignatura->clave_asignatura }})</option>
                        @endforeach
                    </select>
                </label>
                <label>Evidencia
                    <select name="evidencia" class="asignacion-evidencia" required>
                        <option value="">Evidencia sin identificar</option>
                    </select>
                </label>
                <button type="submit" class="btn btn-navy">Vincular rúbrica</button>
            </div>
        </form>
    @endforeach

    @forelse ($formularios as $formulario)
        @php
            $evidencia = $formulario['evidencia'];
            $bloqueada = $formulario['bloqueada'];
            $grupos = $evidencia->grupos;
        @endphp
        <form method="POST" action="{{ route('evaluacion-docente.rubricas.guardar', $evidencia) }}"
            class="panel rubric-card rubrica-form" id="evidencia-{{ $evidencia->id_evidencia }}" data-total="{{ $puntajeTotal }}"
            data-existente="{{ $evidencia->criterios_rubrica_count > 0 ? '1' : '0' }}">
            @csrf
            <input type="hidden" name="firma_rubrica" value="{{ $formulario['firma'] }}">
            <input type="hidden" name="confirmar_reemplazo" value="0">
            <div class="rubric-head">
                <div>
                    <h2>{{ $evidencia->titulo }} <small>({{ $evidencia->codigo }})</small></h2>
                    <small>Rúbrica del archivo: {{ $formulario['codigo'] }}</small><br>
                    <small>
                        {{ $grupos->first()?->asignatura?->nombre_asignatura ?? 'Sin asignatura' }}
                        · Grupo {{ $grupos->pluck('clave_grupo')->join(', ') ?: '—' }}
                    </small>
                </div>
                @if ($formulario['guardada'])
                    <span class="badge badge-ok">✓ Rúbrica guardada</span>
                @elseif ($evidencia->criterios_rubrica_count > 0)
                    <span class="badge badge-warn">Ya tiene rúbrica; al guardar se reemplaza</span>
                @else
                    <span class="badge badge-warn">Sin rúbrica guardada</span>
                @endif
            </div>

            @if ($formulario['errores'])
                <div class="alert alert-err" role="alert">
                    <ul>@foreach ($formulario['errores'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @if ($bloqueada)
                <div class="alert alert-warn" role="status">Esta rúbrica está bloqueada porque ya existen evaluaciones registradas; no se puede reemplazar.</div>
            @endif

            <table class="rubric-table">
                <thead>
                    <tr>
                        <th scope="col">Criterio</th>
                        <th scope="col">Puntaje máximo</th>
                        <th scope="col">Evaluable desde el producto</th>
                        @unless ($bloqueada)<th scope="col">Acción</th>@endunless
                    </tr>
                </thead>
                <tbody class="criterios">
                    @foreach ($formulario['criterios'] as $indice => $criterio)
                        @php $evaluable = filter_var($criterio['evaluable_ia'] ?? true, FILTER_VALIDATE_BOOLEAN); @endphp
                        <tr>
                            <td>
                                <input name="criterios[{{ $indice }}][nombre_criterio]" value="{{ $criterio['nombre_criterio'] ?? '' }}" maxlength="100" aria-label="Nombre del criterio" required @disabled($bloqueada)>
                                @if (filled($criterio['descripcion_criterio'] ?? null))
                                    <input type="hidden" name="criterios[{{ $indice }}][descripcion_criterio]" value="{{ $criterio['descripcion_criterio'] }}" @disabled($bloqueada)>
                                @endif
                            </td>
                            <td><input type="number" class="puntaje" name="criterios[{{ $indice }}][puntaje_maximo]" value="{{ $criterio['puntaje_maximo'] ?? '' }}" min="0.01" max="{{ $puntajeTotal }}" step="0.01" aria-label="Puntaje máximo" required @disabled($bloqueada)></td>
                            <td>
                                <select name="criterios[{{ $indice }}][evaluable_ia]" aria-label="Evaluable desde el producto" @disabled($bloqueada)>
                                    <option value="1" @selected($evaluable)>Sí</option>
                                    <option value="0" @selected(! $evaluable)>No</option>
                                </select>
                            </td>
                            @unless ($bloqueada)<td><button type="button" class="act-btn act-del quitar-criterio">Quitar</button></td>@endunless
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="rubric-note">Evaluable desde el producto: «Sí» significa que la IA evalúa el criterio; «No» (por ejemplo desempeño) significa que la IA no lo evalúa.</p>
            <div class="rubric-actions">
                <span class="rubric-total">Total: <span class="suma">0</span> / {{ $puntajeTotal }}</span>
                @unless ($bloqueada)
                    <div class="rubric-btns">
                        <button type="button" class="btn-outline agregar-criterio">＋ Agregar criterio</button>
                        <button type="submit" class="btn btn-navy">💾 Guardar rúbrica</button>
                    </div>
                @endunless
            </div>
        </form>
    @empty
        <div class="alert alert-warn" role="status">
            No hay rúbricas vinculadas todavía. Si corresponde, elige una asignatura y una evidencia arriba.
        </div>
        <p><a href="{{ route('evidencias.index') }}" class="btn btn-navy cta">Ir a Evidencias</a></p>
    @endforelse

    <form method="POST" action="{{ route('evaluacion-docente.rubricas.descartar') }}" class="page-footer">
        @csrf
        @method('DELETE')
        <a href="{{ route('evaluacion-docente.index') }}" class="btn-outline">← Volver a Evaluación Docente</a>
        <button type="submit" class="btn-outline btn-outline-danger">Descartar importación</button>
    </form>

    <template id="criterioPlantilla">
        <tr>
            <td><input data-campo="nombre_criterio" maxlength="100" aria-label="Nombre del criterio" required></td>
            <td><input data-campo="puntaje_maximo" class="puntaje" type="number" min="0.01" max="{{ $puntajeTotal }}" step="0.01" aria-label="Puntaje máximo" required></td>
            <td>
                <select data-campo="evaluable_ia" aria-label="Evaluable desde el producto">
                    <option value="1">Sí</option>
                    <option value="0">No</option>
                </select>
            </td>
            <td><button type="button" class="act-btn act-del quitar-criterio">Quitar</button></td>
        </tr>
    </template>
@endsection

@section('scripts')
    <script>
        (() => {
            const plantilla = document.getElementById('criterioPlantilla');
            const maximo = {{ $maxCriterios }};
            const evidencias = @json($evidenciasParaAsignar);

            document.querySelectorAll('.asignacion-form').forEach((formulario) => {
                const asignatura = formulario.querySelector('.asignacion-asignatura');
                const evidencia = formulario.querySelector('.asignacion-evidencia');
                const actualizarEvidencias = () => {
                    evidencia.replaceChildren(new Option('Evidencia sin identificar', ''));
                    evidencias.filter((e) => e.asignaturas.some((id) => String(id) === asignatura.value))
                        .forEach((e) => evidencia.add(new Option(
                            `${e.titulo} (${e.codigo || 'sin código'}) — ${e.grupos}`, String(e.id)
                        )));
                };
                asignatura.addEventListener('change', actualizarEvidencias);
                actualizarEvidencias();
            });

            document.querySelectorAll('.rubrica-form').forEach((formulario) => {
                const tbody = formulario.querySelector('.criterios');
                const objetivo = Number(formulario.dataset.total);
                let siguiente = tbody.querySelectorAll('tr').length;
                formulario.addEventListener('submit', (evento) => {
                    if (formulario.dataset.existente !== '1') return;
                    evento.preventDefault();
                    window.confirmarAccion({
                        titulo: '¿Modificar la rúbrica existente?',
                        mensaje: 'La rúbrica actual de esta evidencia será reemplazada por los criterios del formulario.',
                        okLabel: 'Sí, modificar',
                        alConfirmar: () => {
                            formulario.elements.confirmar_reemplazo.value = '1';
                            formulario.submit();
                        },
                    });
                });

                const actualizar = () => {
                    const suma = [...tbody.querySelectorAll('.puntaje')]
                        .reduce((total, campo) => total + (parseFloat(campo.value) || 0), 0);
                    const etiqueta = formulario.querySelector('.rubric-total');
                    formulario.querySelector('.suma').textContent = (Math.round(suma * 100) / 100).toString();
                    etiqueta.classList.toggle('good', Math.abs(suma - objetivo) < 0.005);
                    etiqueta.classList.toggle('bad', Math.abs(suma - objetivo) >= 0.005);
                };

                tbody.addEventListener('input', actualizar);
                tbody.addEventListener('click', (evento) => {
                    if (evento.target.matches('.quitar-criterio')) {
                        evento.target.closest('tr').remove();
                        actualizar();
                    }
                });

                const agregar = formulario.querySelector('.agregar-criterio');
                if (agregar) {
                    agregar.addEventListener('click', () => {
                        if (tbody.querySelectorAll('tr').length >= maximo) return;
                        const fila = plantilla.content.cloneNode(true);
                        fila.querySelectorAll('[data-campo]').forEach((campo) => {
                            campo.name = `criterios[${siguiente}][${campo.dataset.campo}]`;
                        });
                        tbody.appendChild(fila);
                        siguiente++;
                    });
                }

                actualizar();
            });
        })();
    </script>
@endsection
