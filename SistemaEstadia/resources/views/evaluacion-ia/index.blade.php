@extends('layouts.panel')

@section('title', 'Evaluación con IA')

@section('styles')
    @include('partials.dashboard-styles')

    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }
    .alert-warn { background:var(--warn-bg); color:var(--warn-fg); border-color:#f1d28a; }
    .alert a { font-weight:700; text-decoration:underline; }

    .selectors { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; padding:14px 16px; margin-bottom:16px; }
    .field-control {
        width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text);
    }
    .selectors select:last-child { border-color:var(--blue); color:var(--blue); font-weight:700; }

    .rubric-split { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; margin-bottom:16px; }
    .rubric-box { padding:16px 18px; }
    .rubric-box h2 { margin:0 0 4px; font-size:14px; }
    .rubric-box > p { margin:0 0 10px; font-size:12px; color:var(--muted); }
    .rubric-box ul { list-style:none; margin:0; padding:0; }
    .rubric-box li { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-top:1px solid var(--border); font-size:12.5px; }
    .rubric-box li strong { white-space:nowrap; color:var(--navy); }
    .rubric-box .none { color:var(--muted); border-top:0; }
    .chip { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .chip-ia { background:var(--ok-bg); color:var(--ok-fg); }
    .chip-doc { background:var(--warn-bg); color:var(--warn-fg); }

    .table-card { overflow:hidden; margin-bottom:14px; }
    .table-card .table-wrap { border:0; border-radius:0; margin:0; }
    .state { display:inline-block; padding:6px 14px; border-radius:999px; font-size:11.5px; font-weight:700; white-space:nowrap; }
    .s-ok { background:var(--ok-bg); color:var(--ok-fg); }
    .s-wait { background:var(--warn-bg); color:var(--warn-fg); }
    .s-bad { background:var(--bad-bg); color:var(--bad-fg); }
    .btn:disabled { opacity:.5; cursor:not-allowed; filter:none; }
    .act-cell form { margin:0; }
    .act-cell .btn { max-width:none; white-space:nowrap; }
    .doc-status { display:block; margin-top:6px; font-size:12px; }
    .doc-status.err { color:var(--bad-fg); font-weight:700; }
    .doc-status.ok { color:var(--ok-fg); font-weight:700; }
    .saved { margin-top:10px; text-align:left; font-size:12.5px; }
    .saved summary { cursor:pointer; font-weight:700; color:var(--blue); }
    .saved p { margin:8px 0; color:var(--muted); }
    .saved ul { margin:6px 0 0; padding-left:18px; }
    .saved li { margin-bottom:4px; }
    .saved small { display:block; margin-top:8px; color:var(--muted); }
    .info-note { padding:12px 16px; border-radius:10px; background:var(--info-bg); color:var(--info-fg); font-size:12px; border:1px solid #bfd3f2; margin-bottom:10px; }
    [hidden] { display:none !important; }

    .batch { padding:16px 18px; margin-bottom:16px; }
    .batch-top { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; }
    .batch-top h2 { margin:0 0 4px; font-size:14px; }
    .batch-top p { margin:0; font-size:12px; color:var(--muted); max-width:70ch; }
    .batch-top .btn { max-width:none; white-space:nowrap; }
    .batch-msg { margin:10px 0 0; font-size:12.5px; font-weight:700; }
    .batch-msg.err { color:var(--bad-fg); }
    .batch-progress { margin-top:14px; border-top:1px solid var(--border); padding-top:12px; }
    .batch-progress progress { width:100%; height:12px; accent-color:var(--blue); }
    .batch-progress .resumen { margin:8px 0; font-size:13px; font-weight:700; }
    .batch-docs { list-style:none; margin:0; padding:0; font-size:12.5px; }
    .batch-docs li { display:flex; flex-wrap:wrap; gap:6px 10px; padding:6px 0; border-top:1px solid var(--border); }
    .batch-docs .estado { min-width:110px; font-weight:700; }
    .batch-docs .ok { color:var(--ok-fg); }
    .batch-docs .error { color:var(--bad-fg); }
    .batch-docs .procesando { color:var(--blue); }
    .batch-docs .pendiente { color:var(--muted); }
    .batch-docs .detalle { flex-basis:100%; color:var(--bad-fg); font-weight:400; }

    @media (max-width: 760px) {
        .selectors, .rubric-split { grid-template-columns:1fr; }
    }
@endsection

@section('sidebar')
    @include($user->role === 'lider' ? 'partials.lider-sidebar' : 'partials.profesor-sidebar')
@endsection

@section('content')
    @php
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
        $tieneRubricaIa = $criteriosIa->isNotEmpty();
        $puedeEvaluar = $geminiConfigurado && $tieneRubricaIa;
    @endphp

    <h1 class="page-title">Evaluación con IA</h1>
    <p class="page-sub">Evalúa con Gemini los PDFs anonimizados según la rúbrica de la evidencia. El resultado se guarda en la base de datos para el Motor Comparativo</p>

    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif
    @unless ($geminiConfigurado)
        <div class="alert alert-warn" role="status">Falta configurar <code>GEMINI_API_KEY</code> en el archivo <code>.env</code>; sin ella no se puede evaluar.</div>
    @endunless

    <form method="GET" action="{{ route('evaluacion-ia.index') }}" class="panel selectors">
        <select name="asignatura" class="field-control" aria-label="Asignatura" onchange="this.form.grupo.value='';this.form.evidencia.value='';this.form.submit()">
            @forelse ($asignaturas as $a)
                <option value="{{ $a->id_asignatura }}" @selected($asignatura?->id_asignatura === $a->id_asignatura)>{{ $a->nombre_asignatura }}</option>
            @empty
                <option value="">Sin asignaturas asignadas</option>
            @endforelse
        </select>
        <select name="grupo" class="field-control" aria-label="Grupo" onchange="this.form.evidencia.value='';this.form.submit()">
            @forelse ($grupos as $g)
                <option value="{{ $g->id_grupo }}" @selected($grupo?->id_grupo === $g->id_grupo)>{{ $g->clave_grupo }}</option>
            @empty
                <option value="">Sin grupos</option>
            @endforelse
        </select>
        <select name="evidencia" class="field-control" aria-label="Evidencia" onchange="this.form.submit()">
            @forelse ($evidencias as $e)
                <option value="{{ $e->id_evidencia }}" @selected($evidencia?->id_evidencia === $e->id_evidencia)>{{ $e->titulo }}</option>
            @empty
                <option value="">Sin evidencias para este grupo</option>
            @endforelse
        </select>
    </form>

    @if ($evidencia)
        @unless ($tieneRubricaIa)
            <div class="alert alert-warn" role="status">
                «{{ $evidencia->titulo }}» no tiene una rúbrica con criterios evaluables desde el producto, así que no se puede enviar a la IA.
                <a href="{{ route('evidencias.rubrica', $evidencia) }}">Configura la rúbrica</a>.
            </div>
        @endunless

        <div class="rubric-split">
            <section class="panel rubric-box" aria-label="Criterios que evalúa la IA">
                <h2><span class="chip chip-ia">La IA evalúa</span></h2>
                <p>Se envían a Gemini y vuelven con calificación y retroalimentación.</p>
                <ul>
                    @forelse ($criteriosIa as $c)
                        <li><span>{{ $c->nombre_criterio }}</span><strong>{{ $fmt($c->puntaje_maximo) }}</strong></li>
                    @empty
                        <li class="none">Ningún criterio de esta rúbrica es evaluable desde el producto.</li>
                    @endforelse
                </ul>
            </section>
            <section class="panel rubric-box" aria-label="Criterios que califica el docente">
                <h2><span class="chip chip-doc">Lo califica el docente</span></h2>
                <p>No se envían a la IA ni reciben calificación de su parte.</p>
                <ul>
                    @forelse ($criteriosDocente as $c)
                        <li><span>{{ $c->nombre_criterio }}</span><strong>{{ $fmt($c->puntaje_maximo) }}</strong></li>
                    @empty
                        <li class="none">Todos los criterios los evalúa la IA.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        @php $loteActivo = $lote?->estaActivo() ?? false; @endphp
        <section class="panel batch" id="lotePanel" aria-label="Envío del lote a la IA">
            <div class="batch-top">
                <div>
                    <h2>Evaluar el lote completo</h2>
                    <p>
                        Envía a la IA los PDFs sanitizados que aún no tienen evaluación ({{ $pendientesLote }} {{ $pendientesLote === 1 ? 'pendiente' : 'pendientes' }}).
                        Se procesan de uno en uno; si Gemini responde HTTP 503 se reintenta tras 1, 2, 4, 8 y 16 segundos.
                        Puedes seguir usando el sistema: al terminar recibirás una notificación.
                    </p>
                </div>
                <button type="button" class="btn btn-blue" id="btnLote" @disabled(! $puedeEvaluar || $pendientesLote === 0 || $loteActivo)>🚀 Enviar lote a la IA</button>
            </div>
            @if (! $puedeEvaluar)
                <p class="batch-msg err">No se puede enviar: {{ $geminiConfigurado ? 'la evidencia no tiene criterios evaluables por la IA.' : 'falta configurar GEMINI_API_KEY.' }}</p>
            @elseif ($pendientesLote === 0 && ! $loteActivo)
                <p class="batch-msg">No hay PDFs pendientes: todos los sanitizados ya tienen evaluación o faltan por sanitizar.</p>
            @endif
            <p class="batch-msg err" id="loteError" role="alert" hidden></p>
            <div class="batch-progress" id="loteProgreso" role="status" aria-live="polite" @if (! $lote) hidden @endif>
                <progress id="loteBarra" value="0" max="1"></progress>
                <p class="resumen" id="loteResumen"></p>
                <ul class="batch-docs" id="loteDocs"></ul>
            </div>
        </section>

        <section class="panel table-card" aria-label="Documentos a evaluar">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Equipo</th>
                            <th scope="col" class="center">Estado del PDF</th>
                            <th scope="col" class="center">Evaluación con IA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documentos as $doc)
                            @php $listo = $doc->estado_sanitizacion === 'sanitizado'; @endphp
                            <tr>
                                <td><strong>{{ $doc->equipo?->nombre_equipo ?? 'Documento '.$doc->id_corpus }}</strong></td>
                                <td class="center">
                                    @if ($listo)
                                        <span class="state s-ok">✓ Sanitizado</span>
                                    @elseif ($doc->estado_sanitizacion === 'error')
                                        <span class="state s-bad">✕ Error al sanitizar</span>
                                    @else
                                        <span class="state s-wait">⏳ Pendiente de sanitizar</span>
                                    @endif
                                </td>
                                <td class="center act-cell">
                                    @if ($listo)
                                        <form method="POST" action="{{ route('evaluacion-ia.evaluar', $doc) }}" class="js-evaluar" data-bloqueada="{{ $rubricaBloqueada ? '1' : '0' }}">
                                            @csrf
                                            <button type="submit" class="btn btn-green" @disabled(! $puedeEvaluar || $loteActivo)>🤖 {{ $doc->ultimaEvaluacionIa ? 'Volver a evaluar' : 'Evaluar con IA' }}</button>
                                            <span class="doc-status" role="status" aria-live="polite"></span>
                                        </form>
                                        @if ($doc->ultimaEvaluacionIa)
                                            @php $ev = $doc->ultimaEvaluacionIa; @endphp
                                            <details class="saved">
                                                <summary>✓ Guardada: {{ $fmt($ev->calificacion_global) }} / {{ $fmt($criteriosIa->sum('puntaje_maximo')) }} pts</summary>
                                                <p>{{ $ev->retroalimentacion_cualitativa }}</p>
                                                <ul>
                                                    @foreach ($ev->criterios as $detalle)
                                                        <li>
                                                            <strong>{{ $nombresCriterios[$detalle->id_criterio] ?? 'Criterio '.$detalle->id_criterio }}:
                                                                {{ $fmt($detalle->calificacion) }} / {{ $fmt($maximosCriterios[$detalle->id_criterio] ?? 0) }}</strong>
                                                            @if ($detalle->retroalimentacion) — {{ $detalle->retroalimentacion }} @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                <small>Modelo {{ $ev->modelo_llm }} · {{ $ev->fecha_procesamiento?->format('d/m/Y H:i') }} · {{ $fmt($ev->tiempo_procesamiento_segundos) }} s</small>
                                            </details>
                                        @endif
                                    @else
                                        <a href="{{ route('corpus.index', ['asignatura' => $asignatura->id_asignatura, 'grupo' => $grupo->id_grupo, 'evidencia' => $evidencia->id_evidencia]) }}">Sanitizar en «Carga y Sanitización»</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Aún no hay PDFs cargados para esta evidencia. Súbelos en «Carga y Sanitización».</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="info-note">ℹ️ Evaluar envía el PDF sanitizado a Gemini (servicio externo) y puede tardar hasta un par de minutos. Verifica que no contenga nombres, matrículas u otros datos identificables en las páginas posteriores a la portada.</div>
        <div class="info-note">ℹ️ El resultado se guarda en la base de datos. Volver a evaluar un documento reemplaza su evaluación anterior, y con evaluaciones guardadas la rúbrica de la evidencia ya no se puede modificar.</div>
    @else
        <section class="panel"><p class="empty">Selecciona una evidencia de un grupo para evaluar sus documentos.</p></section>
    @endif
@endsection

@section('scripts')
<script>
(() => {
    document.querySelectorAll('.js-evaluar').forEach((formulario) => {
        const boton = formulario.querySelector('button');
        const estado = formulario.querySelector('.doc-status');
        const etiqueta = boton.textContent;
        const mostrar = (texto, tipo = '') => { estado.textContent = texto; estado.className = 'doc-status ' + tipo; };

        async function evaluar() {
            boton.disabled = true;
            boton.textContent = '⏳ Evaluando…';
            mostrar('Enviando a Gemini; puede tardar hasta un par de minutos. No cierres esta página.');

            try {
                const respuesta = await fetch(formulario.action, {
                    method: 'POST',
                    body: new FormData(formulario),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const cuerpo = await respuesta.json().catch(() => ({}));
                if (!respuesta.ok) {
                    throw new Error(cuerpo.message || 'No se pudo completar la evaluación (HTTP ' + respuesta.status + ').');
                }
                mostrar('✓ ' + cuerpo.message, 'ok');
                setTimeout(() => window.location.reload(), 1200);
            } catch (error) {
                mostrar(error.message, 'err');
                boton.disabled = false;
                boton.textContent = etiqueta;
            }
        }

        formulario.addEventListener('submit', (evento) => {
            evento.preventDefault();
            if (boton.disabled) return;
            // Guardar la primera evaluación bloquea la rúbrica; se avisa antes de hacerlo.
            if (formulario.dataset.bloqueada === '0') {
                window.confirmarAccion({
                    titulo: '¿Evaluar y guardar con IA?',
                    mensaje: 'El resultado se guardará en la base de datos y, a partir de ahora, la rúbrica de esta evidencia ya no podrá modificarse. Revisa que sea la definitiva.',
                    okLabel: 'Evaluar y guardar',
                    alConfirmar: evaluar,
                });
                return;
            }
            evaluar();
        });
    });
})();
</script>

@if ($evidencia && $grupo)
<script>
(() => {
    const boton = document.getElementById('btnLote');
    const progreso = document.getElementById('loteProgreso');
    const barra = document.getElementById('loteBarra');
    const resumen = document.getElementById('loteResumen');
    const docs = document.getElementById('loteDocs');
    const error = document.getElementById('loteError');
    const individuales = () => document.querySelectorAll('.js-evaluar button');
    const ICONOS = { pendiente: '⏳ En espera', procesando: '🔄 Evaluando…', ok: '✓ Evaluado', error: '✕ Con error' };
    const urlEstado = (id) => {{ \Illuminate\Support\Js::from(url('evaluacion-ia/lote')) }} + '/' + id;
    const volver = (id) => {
        const url = new URL(window.location.href);
        url.searchParams.set('lote', id);
        window.location.href = url.toString();
    };
    let temporizador = null;
    let seguia = false;

    function pintar(lote) {
        progreso.hidden = false;
        barra.max = Math.max(lote.total, 1);
        barra.value = lote.procesados;
        resumen.textContent = lote.estado === 'terminado'
            ? 'Lote terminado: ' + lote.exitosos + ' de ' + lote.total + ' evaluados' + (lote.fallidos ? ', ' + lote.fallidos + ' con error.' : '.')
            : 'Evaluando el lote: ' + lote.procesados + ' de ' + lote.total + ' PDFs procesados…';
        docs.replaceChildren();
        lote.documentos.forEach((d) => {
            const li = document.createElement('li');
            const equipo = document.createElement('strong'); equipo.textContent = d.equipo;
            const estado = document.createElement('span'); estado.className = 'estado ' + d.estado; estado.textContent = ICONOS[d.estado] || d.estado;
            li.append(equipo, estado);
            if (d.mensaje) { const m = document.createElement('span'); m.className = 'detalle'; m.textContent = d.mensaje; li.append(m); }
            docs.append(li);
        });
    }

    async function consultar(id) {
        try {
            const respuesta = await fetch(urlEstado(id), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!respuesta.ok) return;
            const lote = await respuesta.json();
            pintar(lote);
            if (lote.estado === 'terminado') {
                clearInterval(temporizador);
                // Se recarga para mostrar las evaluaciones guardadas; el parámetro conserva este resumen a la vista.
                if (seguia) setTimeout(() => volver(id), 1500);
            }
        } catch (e) { /* se reintenta en el siguiente ciclo */ }
    }

    function seguir(id) {
        seguia = true;
        if (boton) boton.disabled = true;
        individuales().forEach((b) => { b.disabled = true; });
        consultar(id);
        temporizador = setInterval(() => consultar(id), 3000);
    }

    async function enviar() {
        error.hidden = true;
        boton.disabled = true;
        const datos = new FormData();
        datos.append('_token', {{ \Illuminate\Support\Js::from(csrf_token()) }});
        datos.append('id_evidencia', {{ \Illuminate\Support\Js::from($evidencia->id_evidencia) }});
        datos.append('id_grupo', {{ \Illuminate\Support\Js::from($grupo->id_grupo) }});
        try {
            const respuesta = await fetch({{ \Illuminate\Support\Js::from(route('evaluacion-ia.lote')) }}, {
                method: 'POST', body: datos,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const cuerpo = await respuesta.json().catch(() => ({}));
            if (!respuesta.ok) throw new Error(cuerpo.message || 'No se pudo enviar el lote (HTTP ' + respuesta.status + ').');
            seguir(cuerpo.lote);
        } catch (e) {
            error.textContent = e.message;
            error.hidden = false;
            boton.disabled = false;
        }
    }

    boton?.addEventListener('click', () => {
        if (boton.disabled) return;
        window.confirmarAccion({
            titulo: '¿Enviar el lote a la IA?',
            mensaje: 'Se enviarán a Gemini los PDFs pendientes, de uno en uno, y los resultados se guardarán en la base de datos. '
                + {{ \Illuminate\Support\Js::from($rubricaBloqueada ? 'La rúbrica ya está bloqueada por evaluaciones existentes.' : 'A partir de la primera evaluación guardada, la rúbrica de esta evidencia ya no podrá modificarse. Revisa que sea la definitiva.') }}
                + ' Recibirás una notificación al terminar.',
            okLabel: 'Enviar lote',
            alConfirmar: enviar,
        });
    });

    @if ($lote)
        const inicial = {{ \Illuminate\Support\Js::from(['estado' => $lote->estado, 'total' => $lote->total, 'procesados' => $lote->procesados(), 'exitosos' => $lote->exitosos, 'fallidos' => $lote->fallidos, 'documentos' => $lote->documentos]) }};
        pintar(inicial);
        if (inicial.estado !== 'terminado') seguir({{ $lote->id_lote }});
    @endif
})();
</script>
@endif
@endsection
