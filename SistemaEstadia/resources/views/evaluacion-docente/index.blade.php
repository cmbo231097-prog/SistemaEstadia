@extends('layouts.panel')

@section('title', 'Captura de Evaluación Docente')

@section('styles')
    @include('partials.dashboard-styles')

    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }
    .alert ul { margin:0; padding-left:18px; }

    .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; }
    .import-panel { padding:16px 18px; margin-bottom:16px; }
    .import-panel[hidden] { display:none; }
    .import-panel h2 { margin:0 0 4px; font-size:14px; }
    .import-panel p { margin:0 0 12px; font-size:12px; color:var(--muted); }
    .import-panel code { font-size:11.5px; }
    .import-row { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
    .rubric-status { margin:-6px 0 16px; font-size:12px; color:var(--muted); }

    .selectors { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; padding:14px 16px; margin-bottom:16px; }
    .field-control {
        width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text);
    }
    .selectors select { padding-right:42px; }

    .workspace { display:grid; grid-template-columns:minmax(0, 1.7fr) minmax(300px, 1fr); gap:16px; align-items:start; }
    .viewer { overflow:hidden; position:sticky; top:calc(var(--header-h, 64px) + 12px); }
    .viewer-head { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid var(--border); font-size:13px; }
    .viewer iframe { display:block; width:100%; height:calc(100vh - var(--header-h, 64px) - 12px - 24px - 46px); min-height:420px; border:0; background:#f1f5f9; }

    .side { display:flex; flex-direction:column; gap:16px; }
    .rubric-view .criteria-list { list-style:none; margin:0; padding:0; max-height:46vh; overflow-y:auto; }
    .rubric-view .criteria-list li { padding:10px 18px; border-top:1px solid var(--border); font-size:12.5px; }
    .rubric-view .criterion-head { display:flex; justify-content:space-between; gap:12px; }
    .rubric-view .criterion-head strong { white-space:nowrap; color:var(--navy); }
    .rubric-view .criteria-list p { margin:4px 0 0; font-size:11.5px; color:var(--muted); }
    .rubric-view .criteria-list small { display:block; margin-top:4px; font-size:11px; color:var(--warn-fg); }
    .eval-card { padding:16px 18px; }
    .eval-card h2 { margin:0 0 4px; font-size:14px; }
    .eval-card .hint { margin:0 0 14px; font-size:11.5px; color:var(--muted); }
    .eval-card label { display:block; font-size:12px; font-weight:700; margin:12px 0 5px; }
    .eval-card textarea.field-control { min-height:130px; resize:vertical; }
    .rubro-list { display:flex; flex-direction:column; gap:10px; }
    .rubro { padding:10px 12px; border:1px solid var(--border); border-radius:10px; min-width:0; }
    .rubro.invalid { border-color:#f3b4b4; background:var(--bad-bg); }
    .rubro-head { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
    .rubro-head label { margin:0; flex:1 1 140px; min-width:0; overflow-wrap:anywhere; }
    .rubro-score { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:var(--muted); white-space:nowrap; }
    .rubro-input { width:90px; padding:8px 10px; }
    .rubro-retro { margin-top:8px; }
    .rubro-retro summary { cursor:pointer; font-size:11.5px; font-weight:700; color:var(--blue); }
    .rubro-retro textarea.field-control { min-height:70px; margin-top:6px; }
    .total-box { margin-top:12px; padding:10px 12px; border-radius:8px; background:var(--info-bg); color:var(--info-fg); font-size:13px; }
    .timer { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:6px; font-size:11.5px; color:var(--muted); }
    .timer button { background:none; border:1px solid var(--border); border-radius:6px; padding:5px 10px; font-size:11.5px; font-weight:700; color:var(--blue); }
    .eval-foot { display:flex; justify-content:flex-end; margin-top:16px; }

    .doc-list { list-style:none; margin:0; padding:0; }
    .doc-list li a { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:10px 18px; border-top:1px solid var(--border); font-size:12.5px; color:var(--text); text-decoration:none; }
    .doc-list li:first-child a { border-top:0; }
    .doc-list li a:hover { background:var(--info-bg); }
    .doc-list li.current a { background:var(--info-bg); font-weight:700; }
    .state { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; text-decoration:none; }
    .s-ok { background:var(--ok-bg); color:var(--ok-fg); }
    .s-wait { background:var(--warn-bg); color:var(--warn-fg); }
    .empty-card { padding:30px 18px; text-align:center; color:var(--muted); font-size:13px; }

    @media (max-width: 960px) {
        .workspace { grid-template-columns:1fr; }
        .viewer { position:static; }
        .viewer iframe { height:70vh; }
        .selectors { grid-template-columns:1fr; }
    }
@endsection

@section('sidebar')
    @include($user->role === 'lider' ? 'partials.lider-sidebar' : 'partials.profesor-sidebar')
@endsection

@section('content')
    @php
        $evaluacion = $documento?->evaluacionDocente;
        $evaluados = $documentos->filter(fn ($d) => $d->evaluacionDocente)->count();
    @endphp

    <div class="page-head">
        <div>
            <h1 class="page-title">Captura de Evaluación Docente</h1>
            <p class="page-sub">Revisa el documento anonimizado y registra tu evaluación humana: calificación y retroalimentación por rubro, y tiempo invertido</p>
        </div>
        <button type="button" class="btn btn-navy" id="toggleImport" aria-expanded="{{ $errors->has('archivo') ? 'true' : 'false' }}" aria-controls="importPanel">📥 Importar rúbricas (Excel/CSV)</button>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" data-autocierre>{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-err" role="alert">
            <ul>@foreach (array_unique($errors->all()) as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="panel import-panel" id="importPanel" aria-label="Importar rúbricas" {{ $errors->has('archivo') ? '' : 'hidden' }}>
        <h2>Importar rúbricas desde archivo</h2>
        <p>
            Formato .xlsx, .xls o .csv con las columnas <code>asignatura, tipo_evidencia, orden_criterio, id_criterio, criterio, puntaje_maximo, evaluable_desde_el_producto</code> (también se acepta <code>puntaje_max</code>).
            Cada evidencia se califica sobre {{ $escala }} puntos. Se vinculan las evidencias cuyo código sea <code>asignatura-tipo_evidencia</code> (ej. GDS-EP1);
            después podrás revisar y completar el formulario de cada evidencia antes de guardar. Sin rúbrica guardada no se pueden enviar evidencias a la IA.
        </p>
        <form method="POST" action="{{ route('evaluacion-docente.rubricas.importar') }}" enctype="multipart/form-data" class="import-row">
            @csrf
            <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required aria-label="Archivo de rúbricas">
            <button type="submit" class="btn btn-navy">Subir y revisar</button>
        </form>
    </section>

    <form method="GET" action="{{ route('evaluacion-docente.index') }}" class="panel selectors">
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

    @if ($evidencia && $criterios->isEmpty())
        <section class="panel empty-card" role="status">
            <strong>«{{ $evidencia->titulo }}» aún no tiene rúbrica.</strong><br>
            Para evaluar la práctica primero sube la rúbrica con el botón «Importar rúbricas (Excel/CSV)» de arriba
            o <a href="{{ route('evidencias.rubrica', $evidencia) }}">captúrala manualmente</a>.
            @unless ($evidencia->codigo)
                <br><small>Recuerda asignar a la evidencia su código (ej. GDS-EP1) en «Evidencias» para vincularla con el archivo.</small>
            @endunless
        </section>
    @elseif (! $documento)
        <section class="panel empty-card">
            No hay documentos sanitizados para evaluar en esta evidencia. Carga y sanitiza los PDFs desde «Carga y Sanitización».
        </section>
    @else
        <div class="workspace">
            <section class="panel viewer" aria-label="Documento a evaluar">
                <div class="viewer-head">
                    <strong>{{ $documento->equipo?->nombre_equipo ?? 'Documento' }} · {{ $evidencia->titulo }}</strong>
                    <a class="state s-ok" href="{{ route('corpus.ver', $documento) }}" target="_blank" rel="noopener">Abrir en otra pestaña</a>
                </div>
                <iframe src="{{ route('corpus.ver', $documento) }}" title="PDF anonimizado de {{ $documento->equipo?->nombre_equipo }}"></iframe>
            </section>

            <div class="side">
                <section class="panel rubric-view" aria-label="Rúbrica de la actividad">
                    <div class="viewer-head">
                        <strong>Rúbrica · {{ $evidencia->titulo }}</strong>
                        <span class="state s-ok">{{ rtrim(rtrim(number_format((float) $criterios->sum('puntaje_maximo'), 2, '.', ''), '0'), '.') }} pts</span>
                    </div>
                    <ol class="criteria-list">
                        @foreach ($criterios as $criterio)
                            <li>
                                <div class="criterion-head">
                                    <span>{{ $criterio->nombre_criterio }}</span>
                                    <strong>{{ rtrim(rtrim(number_format((float) $criterio->puntaje_maximo, 2, '.', ''), '0'), '.') }}</strong>
                                </div>
                                @if ($criterio->descripcion_criterio !== $criterio->nombre_criterio)
                                    <p>{{ $criterio->descripcion_criterio }}</p>
                                @endif
                                @unless ($criterio->evaluable_ia)
                                    <small>Criterio que calificas tú; no se evalúa desde el PDF.</small>
                                @endunless
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section class="panel eval-card" aria-label="Evaluación docente">
                    <h2>Tu evaluación</h2>
                    <p class="hint">
                        Califica cada rubro de 0 hasta su puntaje máximo; la calificación total (sobre {{ $escala }}) se calcula sumando los rubros.
                        La retroalimentación de cada rubro es opcional. Los resultados de la IA no se muestran aquí para que tu criterio sea independiente.
                        @if ($evaluacion)
                            <br>Registrada el {{ $evaluacion->fecha_evaluacion?->format('d/m/Y H:i') }}. Si guardas de nuevo, se reemplazará.
                            @if ($evaluacion->criterios->isEmpty())
                                <br>Esta evaluación se capturó antes de calificar por rubro; califica cada rubro para actualizarla.
                            @endif
                        @endif
                    </p>

                    <form method="POST" action="{{ route('evaluacion-docente.store', $documento) }}" id="evalForm">
                        @csrf
                        @php $registrados = $evaluacion?->criterios->keyBy('id_criterio') ?? collect(); @endphp
                        <div class="rubro-list">
                            @foreach ($criterios as $criterio)
                                @php
                                    $id = $criterio->id_criterio;
                                    $maximo = rtrim(rtrim(number_format((float) $criterio->puntaje_maximo, 2, '.', ''), '0'), '.');
                                    $registrado = $registrados->get($id);
                                    $retro = old("criterios.$id.retroalimentacion", $registrado?->retroalimentacion ?? '');
                                @endphp
                                <div class="rubro {{ $errors->has("criterios.$id.calificacion") ? 'invalid' : '' }}">
                                    <div class="rubro-head">
                                        <label for="calificacion_{{ $id }}">{{ $criterio->nombre_criterio }}</label>
                                        <div class="rubro-score">
                                            <input type="number" id="calificacion_{{ $id }}" name="criterios[{{ $id }}][calificacion]"
                                                class="field-control rubro-input" min="0" max="{{ $criterio->puntaje_maximo }}" step="0.01" required
                                                value="{{ old("criterios.$id.calificacion", $registrado?->calificacion) }}">
                                            <span>/ {{ $maximo }}</span>
                                        </div>
                                    </div>
                                    <details class="rubro-retro" {{ filled($retro) || $errors->has("criterios.$id.retroalimentacion") ? 'open' : '' }}>
                                        <summary>Retroalimentación de este rubro (opcional)</summary>
                                        <textarea name="criterios[{{ $id }}][retroalimentacion]" class="field-control"
                                            maxlength="2000" aria-label="Retroalimentación de {{ $criterio->nombre_criterio }}">{{ $retro }}</textarea>
                                    </details>
                                </div>
                            @endforeach
                        </div>

                        <div class="total-box" aria-live="polite">
                            Calificación total: <strong id="totalCalificacion">0</strong> / {{ $escala }}
                        </div>

                        <label for="comentarios_cualitativos">Comentarios generales (opcional)</label>
                        <textarea id="comentarios_cualitativos" name="comentarios_cualitativos" class="field-control"
                            maxlength="5000">{{ old('comentarios_cualitativos', $evaluacion?->comentarios_cualitativos) }}</textarea>

                        @php
                            $segundosGuardados = $evaluacion?->tiempo_evaluacion_minutos !== null ? (int) round((float) $evaluacion->tiempo_evaluacion_minutos * 60) : null;
                            $tiempoActual = old('tiempo_evaluacion', $segundosGuardados !== null ? sprintf('%02d:%02d', intdiv($segundosGuardados, 60), $segundosGuardados % 60) : '');
                        @endphp
                        <label for="tiempo_evaluacion">Tiempo invertido (minutos:segundos)</label>
                        <input type="text" id="tiempo_evaluacion" name="tiempo_evaluacion" class="field-control"
                            inputmode="numeric" maxlength="6" placeholder="mm:ss, ej. 01:50" pattern="[0-9]{1,3}:[0-5][0-9]"
                            title="Minutos y segundos, por ejemplo 01:50" autocomplete="off" value="{{ $tiempoActual }}">
                        <div class="timer">
                            <span>Tiempo en esta pantalla: <strong id="timerLabel">00:00</strong></span>
                            <button type="button" id="usarTiempo">Usar tiempo medido</button>
                        </div>

                        <div class="eval-foot">
                            <button type="submit" class="btn btn-navy">💾 {{ $evaluacion ? 'Actualizar evaluación' : 'Guardar evaluación' }}</button>
                        </div>
                    </form>
                </section>

                <section class="panel" aria-label="Documentos del lote">
                    <div class="viewer-head"><strong>Documentos ({{ $evaluados }}/{{ $documentos->count() }} evaluados)</strong></div>
                    <ul class="doc-list">
                        @foreach ($documentos as $d)
                            <li class="{{ $d->id_corpus === $documento->id_corpus ? 'current' : '' }}">
                                <a href="{{ route('evaluacion-docente.index', ['asignatura' => $asignatura->id_asignatura, 'grupo' => $grupo->id_grupo, 'evidencia' => $evidencia->id_evidencia, 'documento' => $d->id_corpus]) }}">
                                    <span>{{ $d->equipo?->nombre_equipo ?? 'Documento '.$d->id_corpus }}</span>
                                    @if ($d->evaluacionDocente)
                                        <span class="state s-ok">✓ {{ number_format((float) $d->evaluacionDocente->calificacion_global, 2) }}</span>
                                    @else
                                        <span class="state s-wait">Pendiente</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        </div>

        <script>
            (() => {
                const inicio = Date.now();
                const etiqueta = document.getElementById('timerLabel');
                const campo = document.getElementById('tiempo_evaluacion');
                const formato = (segundos) => String(Math.floor(segundos / 60)).padStart(2, '0') + ':' + String(segundos % 60).padStart(2, '0');
                const transcurrido = () => Math.max(Math.floor((Date.now() - inicio) / 1000), 1);

                setInterval(() => { etiqueta.textContent = formato(Math.floor((Date.now() - inicio) / 1000)); }, 1000);

                document.getElementById('usarTiempo').addEventListener('click', () => {
                    campo.value = formato(transcurrido());
                });

                // Un número solo se interpreta como minutos completos («5» → 05:00) y los segundos sueltos se rellenan («1:5» → 01:05).
                campo.addEventListener('blur', () => {
                    const texto = campo.value.trim();
                    if (/^\d{1,3}$/.test(texto)) campo.value = texto.padStart(2, '0') + ':00';
                    else if (/^\d{1,3}:\d$/.test(texto)) campo.value = texto.replace(/^(\d+):(\d)$/, (_, m, sg) => m.padStart(2, '0') + ':0' + sg);
                });

                // Si el campo queda vacío al guardar, se completa con el tiempo medido en pantalla.
                document.getElementById('evalForm').addEventListener('submit', () => {
                    if (!campo.value.trim()) campo.value = formato(transcurrido());
                });

                const total = document.getElementById('totalCalificacion');
                const calificaciones = [...document.querySelectorAll('.rubro-input')];
                const sumar = () => {
                    const suma = calificaciones.reduce((acumulado, entrada) => acumulado + (parseFloat(entrada.value) || 0), 0);
                    total.textContent = String(Math.round(suma * 100) / 100);
                };
                calificaciones.forEach((entrada) => entrada.addEventListener('input', sumar));
                sumar();
            })();
        </script>
    @endif

    <script>
        document.getElementById('toggleImport').addEventListener('click', (evento) => {
            const panel = document.getElementById('importPanel');
            panel.hidden = !panel.hidden;
            evento.currentTarget.setAttribute('aria-expanded', String(!panel.hidden));
        });
    </script>
@endsection
