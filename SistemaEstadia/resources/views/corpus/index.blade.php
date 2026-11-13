@extends('layouts.panel')

@section('title', 'Carga y Sanitización de Corpus')

@section('styles')
    @include('partials.dashboard-styles')

    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }
    .alert-warn { background:#fffaf0; color:#7a4b00; border-color:#f1c27d; }
    .alert-warn a { color:inherit; font-weight:700; }
    .batch-hint { flex-basis:100%; margin:0; font-size:12px; color:#7a4b00; }
    .alert ul { margin:0; padding-left:18px; }

    .selectors { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; padding:14px 16px; margin-bottom:16px; }
    .field-control {
        width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text); padding-right:42px;
    }
    .selectors select:last-child { border-color:var(--blue); color:var(--blue); font-weight:700; }

    .dropzone {
        border:2px dashed #94a3b8; border-radius:12px; background:#fff; text-align:center;
        padding:26px 18px; margin-bottom:16px; transition:background .15s, border-color .15s;
    }
    .dropzone.over { background:var(--info-bg); border-color:var(--blue); }
    .dropzone.disabled { opacity:.55; pointer-events:none; }
    .dropzone .dz-icon { font-size:34px; }
    .dropzone h2 { margin:6px 0 4px; font-size:14px; }
    .dropzone p { margin:0 0 12px; font-size:11.5px; color:var(--muted); }

    .staged { margin-bottom:16px; padding:14px 16px; }
    .staged h3 { margin:0 0 10px; font-size:13px; }
    .staged-row { display:grid; grid-template-columns:1fr 170px auto; gap:10px; align-items:center; padding:8px 0; border-top:1px solid var(--border); font-size:12.5px; }
    .staged-row:first-child { border-top:0; }
    .staged-row .name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:700; }
    .staged-row small { color:var(--muted); font-weight:400; margin-left:6px; }
    .staged-foot { display:flex; justify-content:flex-end; gap:10px; margin-top:12px; flex-wrap:wrap; }
    .btn-ghost { background:#fff; border:1px solid var(--border); color:#334155; border-radius:8px; padding:9px 16px; font-size:12.5px; font-weight:700; }
    .btn:disabled { opacity:.5; cursor:not-allowed; filter:none; }

    .batchbar { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px; padding:14px 18px; margin-bottom:16px; }
    .batchbar strong { font-size:13px; }
    .batchbar .actions { display:flex; gap:10px; flex-wrap:wrap; }
    .batchbar form { margin:0; }

    .table-card { overflow:hidden; margin-bottom:14px; }
    .table-card .table-wrap { border:0; border-radius:0; margin:0; }
    .table-actions { white-space:nowrap; }
    .table-actions form { display:inline; }
    .act-btn { background:none; border:1px solid transparent; font-size:12px; font-weight:700; padding:6px 8px; border-radius:6px; color:var(--blue); text-decoration:none; display:inline-block; }
    .act-btn:hover { background:var(--info-bg); }
    .act-del { color:var(--bad-fg); }
    .act-del:hover { background:var(--bad-bg); }
    .state { display:inline-block; padding:6px 14px; border-radius:999px; font-size:11.5px; font-weight:700; white-space:nowrap; }
    .s-ok { background:var(--ok-bg); color:var(--ok-fg); }
    .s-wait { background:var(--warn-bg); color:var(--warn-fg); }
    .s-bad { background:var(--bad-bg); color:var(--bad-fg); }
    .info-note { padding:12px 16px; border-radius:10px; background:var(--info-bg); color:var(--info-fg); font-size:12px; border:1px solid #bfd3f2; }
    [hidden] { display:none !important; }

    @media (max-width: 760px) {
        .selectors { grid-template-columns:1fr; }
        .staged-row { grid-template-columns:1fr; }
    }
@endsection

@section('sidebar')
    @include($user->role === 'lider' ? 'partials.lider-sidebar' : 'partials.profesor-sidebar')
@endsection

@section('content')
    @php
        $total = $documentos->count();
        $sanitizados = $documentos->where('estado_sanitizacion', 'sanitizado')->count();
        $porProcesar = $total - $sanitizados;
        $puedeSubir = $evidencia && $equipos->isNotEmpty();
        $sinEquipos = $grupo && $evidencia && $equipos->isEmpty();
        // Motivo por el que no se puede sanitizar, mostrado junto al botón.
        $motivoSinLote = match (true) {
            $sinEquipos => "El grupo {$grupo->clave_grupo} no tiene equipos registrados: sin equipos no hay PDFs que anonimizar.",
            ! $evidencia => 'Selecciona una evidencia para poder sanitizar sus PDFs.',
            $total === 0 => 'Esta evidencia aún no tiene PDFs cargados. Sube los archivos para poder sanitizarlos.',
            $porProcesar === 0 => 'Todos los PDFs de esta evidencia ya están sanitizados.',
            default => null,
        };
    @endphp

    <h1 class="page-title">Carga y Sanitización de Corpus Documental</h1>
    <p class="page-sub">Subida masiva de evidencias académicas en PDF, recorte automático de carátula para anonimización y preparación de corpus</p>

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
    <div class="alert alert-err" role="alert" id="formatoAlert" hidden></div>
    @if ($sinEquipos)
        <div class="alert alert-warn" role="alert" id="avisoSinEquipos">
            <strong>No se puede cargar ni anonimizar documentos todavía.</strong>
            El grupo {{ $grupo->clave_grupo }} no tiene equipos registrados y cada PDF debe asociarse a un equipo.
            <a href="{{ route('equipos.index') }}">Registra los equipos del grupo</a> para habilitar la carga y la sanitización.
        </div>
    @endif

    <form method="GET" action="{{ route('corpus.index') }}" class="panel selectors">
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

    <form method="POST" action="{{ route('corpus.store') }}" enctype="multipart/form-data" id="uploadForm" novalidate>
        @csrf
        <input type="hidden" name="id_evidencia" value="{{ $evidencia?->id_evidencia }}">
        <input type="hidden" name="id_grupo" value="{{ $grupo?->id_grupo }}">

        <section class="dropzone {{ $puedeSubir ? '' : 'disabled' }}" id="dropzone" aria-label="Zona de carga de PDFs">
            <div class="dz-icon" aria-hidden="true">📂</div>
            <h2>Arrastra y suelta aquí los archivos PDF descargados de Google Classroom</h2>
            <p>
                Formato exclusivo permitido: .pdf | Capacidad recomendada por actividad: 8 a 10 PDFs (máximo {{ $maxLote }} por envío)
                @unless ($puedeSubir)
                    <br><strong>
                        @if ($grupo && $equipos->isEmpty())
                            El grupo {{ $grupo->clave_grupo }} no tiene equipos registrados. Registra los equipos del grupo para habilitar la carga.
                        @else
                            Selecciona una evidencia de un grupo con equipos registrados para habilitar la carga.
                        @endif
                    </strong>
                @endunless
            </p>
            <input type="file" id="fileInput" name="archivos[]" accept="application/pdf,.pdf" multiple hidden>
            <button type="button" class="btn btn-navy" id="btnExaminar" style="max-width:260px">Examinar Archivos PDF</button>
        </section>

        <section class="panel staged" id="staged" hidden aria-label="PDFs listos para subir">
            <h3>PDFs seleccionados — confirma el equipo de cada uno</h3>
            <div id="stagedList"></div>
            <div class="staged-foot">
                <button type="button" class="btn-ghost" id="btnLimpiar">Cancelar selección</button>
                <button type="submit" class="btn btn-green" id="btnSubir" style="max-width:260px">Subir y Sanitizar</button>
            </div>
        </section>
    </form>

    <section class="panel batchbar" aria-label="Resumen del lote">
        <strong>
            Lote cargado: {{ $total }} {{ $total === 1 ? 'PDF' : 'PDFs' }}
            ({{ $alumnosGrupo }} alumnos en {{ $equipos->count() }} equipos) | {{ $sanitizados }} sanitizados, {{ $porProcesar }} pendientes de procesar
        </strong>
        <div class="actions">
            <form method="POST" action="{{ route('corpus.sanitizar-lote') }}">
                @csrf
                <input type="hidden" name="id_evidencia" value="{{ $evidencia?->id_evidencia }}">
                <input type="hidden" name="id_grupo" value="{{ $grupo?->id_grupo }}">
                <button type="submit" class="btn btn-blue" @disabled($porProcesar === 0) @if ($motivoSinLote) title="{{ $motivoSinLote }}" aria-describedby="motivoSinLote" @endif>✂️ Sanitizar Lote Completo</button>
            </form>
            @if ($evidencia)
                <a href="{{ route('evaluacion-ia.index', ['asignatura' => $asignatura?->id_asignatura, 'grupo' => $grupo?->id_grupo, 'evidencia' => $evidencia->id_evidencia]) }}"
                    class="btn btn-green" style="text-decoration:none">🚀 Ir a Evaluación con IA</a>
            @endif
            @if ($evidencia && $evidencia->criteriosRubrica->where('evaluable_ia', true)->isEmpty())
                <small>Sin rúbrica no se puede evaluar con IA: <a href="{{ route('evidencias.rubrica', $evidencia) }}">configúrala aquí</a> o impórtala desde Evaluación Docente.</small>
            @endif
        </div>
        @if ($motivoSinLote)
            <p class="batch-hint" id="motivoSinLote">ℹ️ {{ $motivoSinLote }}</p>
        @endif
    </section>

    <section class="panel table-card" aria-label="Corpus cargado">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Archivo PDF Original</th>
                        <th scope="col" class="center">Equipo Asignado</th>
                        <th scope="col" class="center">Páginas</th>
                        <th scope="col" class="center">Estado de Sanitización</th>
                        <th scope="col">Evaluación cuantitativa y cualitativa</th>
                        <th scope="col" class="center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documentos as $doc)
                        <tr>
                            <td><strong>{{ $doc->nombre_archivo_original }}</strong></td>
                            <td class="center">{{ $doc->equipo?->nombre_equipo ?? '—' }}</td>
                            <td class="center">{{ $doc->paginas ? $doc->paginas.' págs.' : '—' }}</td>
                            <td class="center">
                                @if ($doc->estado_sanitizacion === 'sanitizado')
                                    <span class="state s-ok">✓ Portada suprimida @if ($doc->paginas)({{ $doc->paginas - 1 }} págs. restantes)@endif</span>
                                @elseif ($doc->estado_sanitizacion === 'error')
                                    <span class="state s-bad">✕ Error al sanitizar</span>
                                @else
                                    <span class="state s-wait">⏳ Pendiente de recorte</span>
                                @endif
                            </td>
                            <td>
                                @if ($doc->ultimaEvaluacionIa)
                                    <strong>{{ number_format((float) $doc->ultimaEvaluacionIa->calificacion_global, 2) }} / {{ \App\Models\CriterioRubrica::PUNTAJE_TOTAL }}</strong>
                                    <details>
                                        <summary>Ver retroalimentación y criterios</summary>
                                        <p>{{ $doc->ultimaEvaluacionIa->retroalimentacion_cualitativa }}</p>
                                        <ul>
                                            @foreach ($doc->ultimaEvaluacionIa->criterios as $detalle)
                                                <li>
                                                    <strong>{{ $detalle->criterio?->nombre_criterio ?? 'Criterio' }}:
                                                        {{ number_format((float) $detalle->calificacion, 2) }}
                                                        / {{ number_format((float) ($detalle->criterio?->puntaje_maximo ?? 0), 2) }}</strong>
                                                    @if ($detalle->retroalimentacion)
                                                        — {{ $detalle->retroalimentacion }}
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                        <small>Modelo {{ $doc->ultimaEvaluacionIa->modelo_llm }} ·
                                            {{ $doc->ultimaEvaluacionIa->fecha_procesamiento?->format('d/m/Y H:i') }}</small>
                                    </details>
                                @else
                                    <span class="state s-wait">Sin evaluación registrada</span>
                                @endif
                            </td>
                            <td class="center table-actions">
                                @if ($doc->estado_sanitizacion === 'sanitizado')
                                    <a class="act-btn" href="{{ route('corpus.ver', $doc) }}" target="_blank" rel="noopener">Ver PDF</a>
                                @else
                                    <form method="POST" action="{{ route('corpus.sanitizar', $doc) }}">
                                        @csrf
                                        <button type="submit" class="act-btn">Sanitizar</button>
                                    </form>
                                @endif
                                /
                                <form method="POST" action="{{ route('corpus.destroy', $doc) }}" id="del-corpus-{{ $doc->id_corpus }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="act-btn act-del"
                                        data-confirm
                                        data-title="Eliminar PDF"
                                        data-message="¿Deseas eliminar «{{ $doc->nombre_archivo_original }}»? Se borrarán el archivo original y el sanitizado."
                                        data-ok-label="Eliminar"
                                        data-submit="#del-corpus-{{ $doc->id_corpus }}">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Aún no hay PDFs cargados para esta evidencia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="info-note">ℹ️ Antes de enviar el archivo a Gemini, verifica que las páginas posteriores a la portada no contengan nombres, matrículas u otros datos identificables. La sanitización actual elimina la primera hoja, pero no detecta datos personales dentro del resto del documento.</div>
@endsection

@section('scripts')
<script>
(() => {
    const EQUIPOS = @json($equiposJs);
    const MAX = {{ $maxLote }};
    const MAX_BYTES = 20 * 1024 * 1024;
    const input = document.getElementById('fileInput');
    const zone = document.getElementById('dropzone');
    const staged = document.getElementById('staged');
    const list = document.getElementById('stagedList');
    const alertBox = document.getElementById('formatoAlert');
    let files = [];

    const esPdf = f => /\.pdf$/i.test(f.name) && (f.type === 'application/pdf' || f.type === '');
    const detectar = nombre => {
        const m = nombre.match(/equipo[\s_\-]*0*(\d+)/i);
        if (!m) return '';
        const hit = EQUIPOS.find(e => ((e.nombre.match(/(\d+)/) || [])[1] || '').replace(/^0+/, '') === m[1]);
        return hit ? String(hit.id) : '';
    };
    const aviso = msg => { alertBox.textContent = msg; alertBox.hidden = !msg; };

    function sync() {
        const dt = new DataTransfer();
        files.forEach(x => dt.items.add(x.file));
        input.files = dt.files;
        staged.hidden = files.length === 0;
        list.innerHTML = '';
        files.forEach((x, i) => {
            const row = document.createElement('div');
            row.className = 'staged-row';
            const name = document.createElement('div');
            name.className = 'name';
            name.textContent = x.file.name;
            const size = document.createElement('small');
            size.textContent = (x.file.size / 1048576).toFixed(1) + ' MB';
            name.appendChild(size);
            const sel = document.createElement('select');
            sel.name = 'equipos[]';
            sel.className = 'field-control';
            sel.setAttribute('aria-label', 'Equipo de ' + x.file.name);
            const vacio = new Option('Selecciona equipo…', '');
            sel.add(vacio);
            EQUIPOS.forEach(e => sel.add(new Option(e.nombre, e.id)));
            sel.value = x.equipo;
            sel.addEventListener('change', () => { x.equipo = sel.value; });
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'act-btn act-del';
            rm.textContent = 'Quitar';
            rm.addEventListener('click', () => { files.splice(i, 1); sync(); });
            row.append(name, sel, rm);
            list.appendChild(row);
        });
    }

    function agregar(nuevos) {
        const rechazados = [];
        [...nuevos].forEach(f => {
            if (!esPdf(f)) { rechazados.push(f.name); return; }
            if (f.size > MAX_BYTES) { rechazados.push(f.name + ' (supera 20 MB)'); return; }
            if (files.length >= MAX) { rechazados.push(f.name + ' (máximo ' + MAX + ' por envío)'); return; }
            if (files.some(x => x.file.name === f.name && x.file.size === f.size)) return;
            files.push({ file: f, equipo: detectar(f.name) });
        });
        aviso(rechazados.length ? 'Formato no admitido: solo se aceptan archivos PDF. No se agregó: ' + rechazados.join(', ') : '');
        sync();
    }

    document.getElementById('btnExaminar').addEventListener('click', () => input.click());
    input.addEventListener('change', () => agregar(input.files));
    document.getElementById('btnLimpiar').addEventListener('click', () => { files = []; aviso(''); sync(); });
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('over'); }));
    zone.addEventListener('drop', e => agregar(e.dataTransfer.files));

    document.getElementById('uploadForm').addEventListener('submit', e => {
        if (files.some(x => !x.equipo)) { e.preventDefault(); aviso('Asigna un equipo a cada PDF antes de subir.'); return; }
        if (new Set(files.map(x => x.equipo)).size !== files.length) { e.preventDefault(); aviso('Cada equipo solo puede tener un PDF por evidencia.'); return; }
        document.getElementById('btnSubir').disabled = true;
        document.getElementById('btnSubir').textContent = 'Sanitizando…';
    });
})();
</script>
@endsection
