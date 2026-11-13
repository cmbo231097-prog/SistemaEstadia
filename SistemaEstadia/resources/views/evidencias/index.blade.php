@extends('layouts.panel')

@section('title', 'Gestión de Evidencias')

@section('styles')
    @include('partials.dashboard-styles')

    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }

    .toolbar { display:flex; flex-wrap:wrap; gap:12px; padding:16px 18px; margin-bottom:18px; align-items:center; }
    .toolbar .search { flex:1 1 230px; }
    .field-control {
        width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text);
    }
    .toolbar select { flex:0 1 210px; width:auto; padding-right:42px; }
    .toolbar .btn { flex:0 0 auto; min-width:170px; }

    .table-card { overflow:hidden; margin-bottom:14px; }
    .table-card .table-wrap { border:0; border-radius:0; margin:0; }
    .table-foot {
        display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px;
        padding:12px 16px; border-top:1px solid var(--border); font-size:12px; color:var(--muted);
    }
    .table-actions { white-space:nowrap; }
    .table-actions form { display:inline; }
    .act-btn { background:none; border:1px solid transparent; font-size:12px; font-weight:700; padding:6px 8px; border-radius:6px; }
    .act-edit { color:var(--blue); }
    .act-edit:hover { background:var(--info-bg); }
    .act-del { color:var(--bad-fg); }
    .act-del:hover { background:var(--bad-bg); }

    .tipo-badge { display:inline-block; padding:5px 11px; border-radius:999px; background:var(--info-bg); color:var(--info-fg); font-size:11.5px; font-weight:700; white-space:nowrap; }
    .mf-field textarea.field-control { resize:vertical; min-height:96px; }

    .modal.modal-form { max-width:900px; padding:0; overflow:hidden; max-height:calc(100vh - 32px); display:flex; flex-direction:column; }
    .mf-head { background:var(--navy); color:#fff; display:flex; justify-content:space-between; align-items:center; padding:14px 20px; }
    .mf-head h3 { margin:0; font-size:15px; }
    .mf-close { background:none; border:0; color:#fff; font-size:20px; line-height:1; padding:4px 8px; }
    .mf-body { padding:18px 22px; overflow-y:auto; }
    .mf-intro { margin:0 0 14px !important; font-size:12px !important; color:var(--muted) !important; }
    .mf-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 16px; }
    .mf-full { grid-column:1 / -1; }
    .mf-field label { display:block; font-size:12px; font-weight:700; margin-bottom:5px; }
    .mf-field.invalid .field-control { border-color:#dc2626; }
    .mf-error { color:#b91c1c; font-size:11.5px; margin-top:4px; }
    .mf-foot { display:flex; justify-content:flex-end; gap:10px; padding:14px 22px; border-top:1px solid var(--border); flex-wrap:wrap; }
    .mf-foot button { border-radius:8px; padding:10px 22px; font-size:13px; font-weight:700; border:1px solid var(--border); }
    .mf-cancel { background:#fff; color:#334155; }
    .mf-save { background:var(--navy); color:#fff; border-color:var(--navy) !important; }
    .mf-save:hover { filter:brightness(1.1); }
    [hidden] { display:none !important; }

    /* Rúbrica integrada en el formulario */
    .rub-box { border:1px solid var(--border); border-radius:10px; padding:14px; background:#f8fafc; }
    .rub-box > label { font-size:13px; }
    .rub-tools { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin:8px 0 10px; }
    .rub-pick { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:10px; font-size:12px; }
    .rub-pick select { flex:1 1 240px; width:auto; }
    .rub-msg { margin:0 0 8px; font-size:12px; color:var(--muted); }
    .rub-msg.ok { color:var(--ok-fg); }
    .rub-msg.bad { color:var(--bad-fg); font-weight:700; }
    .rub-grid { display:grid; grid-template-columns:minmax(0,1fr) 130px 170px 76px; gap:8px; align-items:start; }
    .rub-grid > * { min-width:0; }
    .rub-head { font-size:11px; font-weight:700; color:var(--muted); padding:0 8px 4px; }
    .rub-row { padding:8px 0; border-top:1px solid var(--border); }
        .rub-row .act-btn { background:#fff; border:1px solid #f3b4b4; color:var(--bad-fg); padding:9px 8px; }
    /* overflow-y:auto también recorta en horizontal: el margen interno deja espacio al anillo de enfoque (3 px + 2 px de separación) */
    .rub-list { max-height:300px; overflow-y:auto; padding:0 8px; }
    .rub-total { margin-top:10px; font-size:13px; font-weight:700; }
    .rub-total.good { color:var(--ok-fg); }
    .rub-total.bad { color:var(--bad-fg); }
    .rub-note { padding:10px 12px; border-radius:8px; background:var(--warn-bg); color:var(--warn-fg); font-size:12px; }
    .rub-box.invalid { border-color:#dc2626; }
    .rub-summary { white-space:nowrap; font-size:12px; }

    @media (max-width: 820px) {
        .rub-head { display:none; }
        .rub-grid { grid-template-columns:1fr 1fr; }
        .rub-grid > :nth-child(1), .rub-grid > :nth-child(4) { grid-column:1 / -1; }
    }
    @media (max-width: 560px) {
        .mf-grid { grid-template-columns:1fr; }
        .toolbar select, .toolbar .btn { flex:1 1 100%; }
    }
@endsection

@section('sidebar')
    @include($user->role === 'lider' ? 'partials.lider-sidebar' : 'partials.profesor-sidebar')
@endsection

@section('content')
    <h1 class="page-title">Catálogo de Evidencias de Aprendizaje</h1>
    <p class="page-sub">Parametrización de actividades académicas, rúbrica de evaluación y asignación por grupos de clase</p>

    @if (session('status'))
        <div class="alert alert-ok" role="status" data-autocierre>{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('evidencias.index') }}" class="panel toolbar" role="search">
        <input type="search" name="q" value="{{ $filtros['q'] }}" class="field-control search"
            placeholder="🔍 Buscar por evidencia..." aria-label="Buscar por evidencia">
        <select name="asignatura" class="field-control" aria-label="Filtrar por asignatura" onchange="this.form.submit()">
            <option value="">Todas las asignaturas</option>
            @foreach ($asignaturas as $asignatura)
                <option value="{{ $asignatura->getKey() }}" @selected($filtros['asignatura'] === (string) $asignatura->getKey())>{{ $asignatura->nombre_asignatura }}</option>
            @endforeach
        </select>
        <select name="grupo" class="field-control" aria-label="Filtrar por grupo" onchange="this.form.submit()">
            <option value="">Todos los grupos</option>
            @foreach ($grupos as $grupo)
                <option value="{{ $grupo->getKey() }}" @selected($filtros['grupo'] === (string) $grupo->getKey())>{{ $grupo->clave_grupo }} — {{ $grupo->asignatura?->nombre_asignatura }}</option>
            @endforeach
        </select>
        <button type="button" class="btn btn-navy" id="btnNueva">＋ Nueva Evidencia</button>
    </form>

    <section class="panel table-card" aria-label="Catálogo de evidencias">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Evidencia / Actividad</th>
                        <th scope="col">Asignatura</th>
                        <th scope="col">Grupo</th>
                        <th scope="col" class="center">Tipo de Evidencia</th>
                        <th scope="col">Rúbrica</th>
                        <th scope="col">Fecha Límite</th>
                        <th scope="col" class="center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($evidencias as $evidencia)
                        @php $grupoEv = $evidencia->grupos->first(); @endphp
                        <tr>
                            <td class="ev-name">{{ $evidencia->titulo }}@if ($evidencia->codigo) <small>({{ $evidencia->codigo }})</small>@endif</td>
                            <td>{{ $grupoEv?->asignatura?->nombre_asignatura ?? '—' }}</td>
                            <td>{{ $evidencia->grupos->pluck('clave_grupo')->join(', ') ?: '—' }}</td>
                            <td class="center"><span class="tipo-badge">{{ $tipos[$evidencia->tipo_evidencia] ?? $evidencia->tipo_evidencia }}</span></td>
                            <td class="rub-summary">
                                @if ($evidencia->criterios_rubrica_count > 0)
                                    {{ $evidencia->criterios_rubrica_count }} criterios · {{ rtrim(rtrim(number_format((float) $evidencia->puntaje_rubrica, 2, '.', ''), '0'), '.') }} pts
                                @else
                                    <span class="badge b-bad">Sin rúbrica</span>
                                @endif
                            </td>
                            <td>{{ $evidencia->fecha_limite?->format('d/m/Y H:i') }}</td>
                            <td class="center table-actions">
                                <a class="act-btn" href="{{ route('evidencias.rubrica', $evidencia) }}">
                                    Rúbrica ({{ $evidencia->criterios_rubrica_count }})
                                </a>
                                <button type="button" class="act-btn act-edit btn-editar"
                                    data-action="{{ route('evidencias.update', $evidencia) }}"
                                    data-id="{{ $evidencia->getKey() }}"
                                    data-titulo="{{ $evidencia->titulo }}"
                                    data-codigo="{{ $evidencia->codigo }}"
                                    data-tipo="{{ $evidencia->tipo_evidencia }}"
                                    data-bloqueada="{{ ($bloqueadas[$evidencia->getKey()] ?? false) ? 1 : 0 }}"
                                    data-criterios="{{ $evidencia->criteriosRubrica->map(fn ($c) => [
                                        'nombre_criterio' => $c->nombre_criterio,
                                        'descripcion_criterio' => $c->descripcion_criterio,
                                        'puntaje_maximo' => (float) $c->puntaje_maximo,
                                        'evaluable_ia' => (bool) $c->evaluable_ia,
                                    ])->values()->toJson() }}"
                                    data-fecha="{{ $evidencia->fecha_limite?->format('Y-m-d\TH:i') }}"
                                    data-grupo="{{ $grupoEv?->getKey() }}"
                                    data-asignatura="{{ $grupoEv?->id_asignatura }}">Editar</button>
                                <form method="POST" action="{{ route('evidencias.destroy', $evidencia) }}" id="del-evidencia-{{ $evidencia->getKey() }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                                    <input type="hidden" name="filtro_asignatura" value="{{ $filtros['asignatura'] }}">
                                    <input type="hidden" name="filtro_grupo" value="{{ $filtros['grupo'] }}">
                                    <input type="hidden" name="page" value="{{ $evidencias->currentPage() }}">
                                    <button type="button" class="act-btn act-del"
                                        data-confirm
                                        data-title="Eliminar evidencia"
                                        data-message="¿Deseas eliminar «{{ $evidencia->titulo }}»? Solo será posible si no tiene documentos ni evaluaciones vinculados."
                                        data-ok-label="Eliminar"
                                        data-submit="#del-evidencia-{{ $evidencia->getKey() }}">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">No se encontraron evidencias con los filtros aplicados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">
            <span>
                @if ($evidencias->total() > 0)
                    Mostrando {{ $evidencias->firstItem() }} a {{ $evidencias->lastItem() }} de {{ $evidencias->total() }} evidencias configuradas
                @else
                    Sin evidencias configuradas
                @endif
            </span>
            {{ $evidencias->links('partials.pagination') }}
        </div>
    </section>

    <div class="modal-overlay" id="evidenciaModal" role="dialog" aria-modal="true" aria-labelledby="evidenciaModalTitle">
        <form class="modal modal-form" method="POST" action="{{ route('evidencias.store') }}" id="evidenciaForm" novalidate>
            @csrf
            <input type="hidden" name="_method" value="PUT" id="eMethod" disabled>
            <input type="hidden" name="_modo" value="{{ old('_modo', 'crear') }}" id="eModo">
            <input type="hidden" name="_id" value="{{ old('_id') }}" id="eId">
            <input type="hidden" name="q" value="{{ $filtros['q'] }}">
            <input type="hidden" name="filtro_asignatura" value="{{ $filtros['asignatura'] }}" id="eFiltroAsignatura" disabled>
            <input type="hidden" name="filtro_grupo" value="{{ $filtros['grupo'] }}" id="eFiltroGrupo" disabled>
            <input type="hidden" name="page" value="{{ $evidencias->currentPage() }}" id="ePage" disabled>

            <div class="mf-head">
                <h3 id="evidenciaModalTitle">Registrar Nueva Evidencia</h3>
                <button type="button" class="mf-close" id="evidenciaModalClose" aria-label="Cerrar">✕</button>
            </div>
            <div class="mf-body">
                <p class="mf-intro">Parametrice la actividad académica, el grupo destinatario y la rúbrica con la que se evaluará</p>
                <div class="mf-grid">
                    @php $err = fn ($campo) => $errors->first($campo); @endphp
                    <div class="mf-field mf-full {{ $err('titulo') ? 'invalid' : '' }}">
                        <label for="eTitulo">Título o Nombre de la Evidencia *</label>
                        <input type="text" id="eTitulo" name="titulo" class="field-control" maxlength="120" required
                            placeholder="Ej. Práctica 4: Grafos y Algoritmo de Dijkstra" value="{{ old('titulo') }}">
                        @if ($err('titulo'))<div class="mf-error">{{ $err('titulo') }}</div>@endif
                    </div>
                    <div class="mf-field">
                        <label for="eAsignatura">Asignatura vinculada *</label>
                        <select id="eAsignatura" class="field-control" required>
                            @foreach ($asignaturas as $asignatura)
                                <option value="{{ $asignatura->getKey() }}">{{ $asignatura->nombre_asignatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mf-field {{ $err('id_grupo') ? 'invalid' : '' }}">
                        <label for="eGrupo">Grupo asignado *</label>
                        <select id="eGrupo" name="id_grupo" class="field-control" required></select>
                        @if ($err('id_grupo'))<div class="mf-error">{{ $err('id_grupo') }}</div>@endif
                    </div>
                    <div class="mf-field mf-full {{ $err('codigo') ? 'invalid' : '' }}">
                        <label for="eCodigo">Código de la evidencia (para importar rúbricas)</label>
                        <input type="text" id="eCodigo" name="codigo" class="field-control" maxlength="30" required
                            placeholder="Asignatura-Evidencia, ej. GDS-EP1" value="{{ old('codigo') }}">
                        @if ($err('codigo'))<div class="mf-error">{{ $err('codigo') }}</div>@endif
                    </div>
                    <div class="mf-field {{ $err('tipo_evidencia') ? 'invalid' : '' }}">
                        <label for="eTipo">Tipo de Evidencia *</label>
                        <select id="eTipo" name="tipo_evidencia" class="field-control" required>
                            @foreach ($tipos as $clave => $nombre)
                                <option value="{{ $clave }}" @selected(old('tipo_evidencia', 'reporte') === $clave)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                        @if ($err('tipo_evidencia'))<div class="mf-error">{{ $err('tipo_evidencia') }}</div>@endif
                    </div>
                    <div class="mf-field mf-full {{ $err('fecha_limite') ? 'invalid' : '' }}">
                        <label for="eFecha">Fecha Límite *</label>
                        <input type="datetime-local" id="eFecha" name="fecha_limite" class="field-control" required value="{{ old('fecha_limite') }}">
                        @if ($err('fecha_limite'))<div class="mf-error">{{ $err('fecha_limite') }}</div>@endif
                    </div>
                    @php
                        $rubErrores = collect($errors->getMessages())
                            ->filter(fn ($mensajes, $clave) => str_starts_with($clave, 'criterios'))
                            ->flatten()->unique()->values();
                    @endphp
                    <div class="mf-field mf-full rub-box {{ $rubErrores->isNotEmpty() ? 'invalid' : '' }}" id="rubricaBox">
                        <label>Rúbrica de evaluación * <span class="member-hint">(los puntos deben sumar exactamente {{ $puntajeTotal }})</span></label>
                        <div class="rub-note" id="rubricaBloqueada" hidden>
                            Esta evidencia ya tiene evaluaciones registradas: su rúbrica se conserva sin cambios para proteger el historial.
                        </div>
                        <div id="rubricaEditor">
                            <div class="rub-tools">
                                <button type="button" class="btn-outline" id="rAdjuntar">📎 Adjuntar archivo (.xlsx, .xls, .csv)</button>
                                <button type="button" class="btn-outline" id="rAgregar">＋ Agregar criterio</button>
                                <input type="file" id="rArchivo" accept=".xlsx,.xls,.csv" hidden>
                            </div>
                            <div class="rub-pick" id="rSelectorWrap" hidden>
                                <label for="rSelector"><strong>El archivo incluye varias rúbricas. Elige la de esta evidencia:</strong></label>
                                <select id="rSelector" class="field-control"></select>
                            </div>
                            <p class="rub-msg" id="rMensaje" role="status">Adjunta un archivo con el formato de rúbricas o agrega los criterios manualmente.</p>
                            <div class="rub-grid rub-head" aria-hidden="true">
                                <span>Criterio</span><span>Puntaje máximo</span><span>Evaluable desde el producto</span><span></span>
                            </div>
                            <div class="rub-list" id="rLista"></div>
                            <div class="rub-total" id="rTotal">Total: <span id="rSuma">0</span> / {{ $puntajeTotal }}</div>
                            <p class="member-hint">Evaluable desde el producto: «Sí» significa que la IA evalúa ese criterio; «No» significa que la IA no lo evalúa (lo califica el docente).</p>
                        </div>
                        @foreach ($rubErrores->take(4) as $mensajeRubrica)
                            <div class="mf-error">{{ $mensajeRubrica }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="mf-foot">
                <button type="button" class="mf-cancel" id="evidenciaModalCancel">Cancelar</button>
                <button type="submit" class="mf-save" id="evidenciaSave">💾 Guardar Evidencia</button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const overlay = document.getElementById('evidenciaModal');
            const form = document.getElementById('evidenciaForm');
            const $ = (id) => document.getElementById(id);
            const storeUrl = @json(route('evidencias.store'));
            const reabrir = @json($reabrir);
            const grupos = @json($gruposJs);
            const grupoPrevio = @json((string) old('id_grupo', ''));
            const criteriosPrevios = @json($criteriosPrevios);
            const leerUrl = @json(route('evidencias.rubrica.leer'));
            const PUNTAJE_TOTAL = {{ $puntajeTotal }};
            const MAX_CRITERIOS = {{ $maxCriterios }};
            let lastFocus = null;
            let siguiente = 0;
            let rubricasArchivo = [];
            let codigoAutomatico = '';
            let rubricaExistente = false;

            // ---- Rúbrica integrada: criterios editables que se envían junto con la evidencia.
            function mensajeRubrica(texto, tipo = '') {
                const caja = $('rMensaje');
                caja.textContent = texto;
                caja.className = 'rub-msg ' + tipo;
            }

            function actualizarSuma() {
                const suma = [...$('rLista').querySelectorAll('.r-puntos')]
                    .reduce((total, campo) => total + (parseFloat(campo.value) || 0), 0);
                const redondeada = Math.round(suma * 100) / 100;
                $('rSuma').textContent = redondeada;
                $('rTotal').classList.toggle('good', Math.abs(redondeada - PUNTAJE_TOTAL) < 0.005);
                $('rTotal').classList.toggle('bad', Math.abs(redondeada - PUNTAJE_TOTAL) >= 0.005);
                return redondeada;
            }

            function agregarCriterio(datos = {}) {
                if ($('rLista').children.length >= MAX_CRITERIOS) {
                    mensajeRubrica('La rúbrica admite como máximo ' + MAX_CRITERIOS + ' criterios.', 'bad');
                    return;
                }
                const i = siguiente++;
                const fila = document.createElement('div');
                fila.className = 'rub-grid rub-row';
                const evaluable = datos.evaluable_ia === false || datos.evaluable_ia === 0 || datos.evaluable_ia === '0' ? '0' : '1';

                const nombre = document.createElement('input');
                nombre.className = 'field-control';
                nombre.name = `criterios[${i}][nombre_criterio]`;
                nombre.maxLength = 100;
                nombre.required = true;
                nombre.placeholder = 'Criterio';
                nombre.setAttribute('aria-label', 'Nombre del criterio');
                nombre.value = datos.nombre_criterio || '';

                // La descripción ya no se captura: se conserva la existente o, si no hay, el servidor usa el nombre del criterio.
                let descripcion = null;
                if (datos.descripcion_criterio) {
                    descripcion = document.createElement('input');
                    descripcion.type = 'hidden';
                    descripcion.name = `criterios[${i}][descripcion_criterio]`;
                    descripcion.value = datos.descripcion_criterio;
                }

                const puntos = document.createElement('input');
                puntos.className = 'field-control r-puntos';
                puntos.type = 'number';
                puntos.name = `criterios[${i}][puntaje_maximo]`;
                puntos.min = '0.01';
                puntos.max = String(PUNTAJE_TOTAL);
                puntos.step = '0.01';
                puntos.required = true;
                puntos.placeholder = 'Puntos';
                puntos.setAttribute('aria-label', 'Puntos máximos');
                puntos.value = datos.puntaje_maximo ?? '';
                puntos.addEventListener('input', actualizarSuma);

                const ia = document.createElement('select');
                ia.className = 'field-control';
                ia.name = `criterios[${i}][evaluable_ia]`;
                ia.setAttribute('aria-label', 'Evaluable desde el producto');
                ia.add(new Option('Sí', '1'));
                ia.add(new Option('No', '0'));
                ia.value = evaluable;

                const quitar = document.createElement('button');
                quitar.type = 'button';
                quitar.className = 'act-btn';
                quitar.textContent = 'Quitar';
                quitar.addEventListener('click', () => { fila.remove(); actualizarSuma(); });

                fila.append(nombre, puntos, ia, quitar);
                if (descripcion) fila.append(descripcion);
                $('rLista').append(fila);
                actualizarSuma();
            }

            function cargarCriterios(lista) {
                $('rLista').replaceChildren();
                siguiente = 0;
                Object.values(lista || {}).forEach((criterio) => agregarCriterio(criterio));
                actualizarSuma();
            }

            function aplicarRubricaDelArchivo(rubrica) {
                cargarCriterios(rubrica.criterios);
                // El código se completa solo mientras el usuario no lo haya escrito a mano.
                const codigoActual = $('eCodigo').value.trim();
                if (codigoActual === '' || codigoActual === codigoAutomatico) {
                    $('eCodigo').value = rubrica.codigo;
                    codigoAutomatico = rubrica.codigo;
                }
                const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().replace(/\s+/g, ' ').toLowerCase();
                const coincidencias = [...$('eAsignatura').options].filter((opcion) => normalizar(opcion.textContent) === normalizar(rubrica.asignatura));
                let avisoAsignatura;
                if (coincidencias.length === 1 && $('eModo').value === 'crear') {
                    if ($('eAsignatura').value !== coincidencias[0].value) {
                        $('eAsignatura').value = coincidencias[0].value;
                        poblarGrupos($('eAsignatura').value);
                    }
                    avisoAsignatura = ' Se seleccionó la asignatura coincidente; verifica el grupo.';
                } else if (coincidencias.length === 1 && $('eAsignatura').value === coincidencias[0].value) {
                    avisoAsignatura = ' La asignatura coincide.';
                } else {
                    if ($('eModo').value === 'crear') {
                        $('eAsignatura').selectedIndex = -1;
                        poblarGrupos('');
                    }
                    avisoAsignatura = ' Verifica o elige manualmente la asignatura y el grupo.';
                }
                mensajeRubrica(
                    'Se cargaron ' + rubrica.criterios.length + ' criterios de ' + rubrica.codigo
                    + ' desde el archivo. Revísalos antes de guardar.' + avisoAsignatura, 'ok'
                );
            }

            function marcarBloqueada(bloqueada) {
                $('rubricaBloqueada').hidden = !bloqueada;
                $('rubricaEditor').hidden = bloqueada;
                if (bloqueada) cargarCriterios([]);
            }

            $('rAgregar').addEventListener('click', () => agregarCriterio());
            $('rAdjuntar').addEventListener('click', () => {
                if (!rubricaExistente) {
                    $('rArchivo').click();
                    return;
                }
                confirmarAccion({
                    titulo: '¿Modificar la rúbrica existente?',
                    mensaje: 'Al cargar el archivo se reemplazarán los criterios del formulario. Los cambios se guardarán solo al guardar la evidencia.',
                    okLabel: 'Sí, modificar',
                    alConfirmar: () => $('rArchivo').click(),
                });
            });

            $('rArchivo').addEventListener('change', async () => {
                const archivo = $('rArchivo').files[0];
                if (!archivo) return;

                const datos = new FormData();
                datos.append('archivo', archivo);
                datos.append('_token', form.querySelector('input[name="_token"]').value);
                mensajeRubrica('Leyendo el archivo…');
                $('rAdjuntar').disabled = true;

                try {
                    const respuesta = await fetch(leerUrl, { method: 'POST', body: datos, headers: { Accept: 'application/json' } });
                    const cuerpo = await respuesta.json().catch(() => ({}));
                    if (!respuesta.ok) {
                        const errores = Object.values(cuerpo.errors || {}).flat();
                        mensajeRubrica(errores.join(' ') || cuerpo.message || 'No se pudo leer el archivo.', 'bad');
                        return;
                    }

                    rubricasArchivo = cuerpo.rubricas;
                    const varias = rubricasArchivo.length > 1;
                    $('rSelectorWrap').hidden = !varias;
                    $('rSelector').required = varias;
                    $('rSelector').replaceChildren();
                    if (varias) $('rSelector').add(new Option('Rúbrica sin identificar', ''));
                    rubricasArchivo.forEach((r, i) => $('rSelector').add(new Option(r.codigo + ' — ' + r.criterios.length + ' criterios', String(i))));

                    // Se prefiere la rúbrica cuyo código coincide con el de la evidencia.
                    const codigo = $('eCodigo').value.trim().toUpperCase();
                    const coincide = rubricasArchivo.findIndex((r) => r.codigo === codigo);
                    if (coincide >= 0 || !varias) {
                        $('rSelector').value = String(coincide >= 0 ? coincide : 0);
                        aplicarRubricaDelArchivo(rubricasArchivo[Number($('rSelector').value)]);
                    } else {
                        $('rSelector').value = '';
                        mensajeRubrica('El archivo contiene varias rúbricas. Elige la correspondiente antes de continuar.');
                    }
                } catch (error) {
                    mensajeRubrica('No se pudo leer el archivo. Inténtalo de nuevo.', 'bad');
                } finally {
                    $('rAdjuntar').disabled = false;
                    $('rArchivo').value = '';
                }
            });

            $('rSelector').addEventListener('change', () => {
                if ($('rSelector').value === '') return;
                const rubrica = rubricasArchivo[Number($('rSelector').value)];
                if (rubrica) aplicarRubricaDelArchivo(rubrica);
            });

            function poblarGrupos(asignatura, seleccionado = '') {
                const selector = $('eGrupo');
                selector.replaceChildren();
                grupos.filter((g) => g.asignatura === String(asignatura)).forEach((g) => selector.add(new Option(g.clave, g.id)));
                selector.value = seleccionado;
                if (selector.selectedIndex < 0) selector.selectedIndex = 0;
            }

            function asignaturaDeGrupo(id) {
                const grupo = grupos.find((g) => g.id === String(id));
                return grupo ? grupo.asignatura : '';
            }

            function configurar(modo, contexto) {
                const editar = modo === 'editar';
                form.action = editar ? contexto.action : storeUrl;
                $('eMethod').disabled = !editar;
                $('eModo').value = modo;
                $('eId').value = editar ? (contexto.id || '') : '';
                $('eFiltroAsignatura').disabled = !editar;
                $('eFiltroGrupo').disabled = !editar;
                $('ePage').disabled = !editar;
                $('evidenciaModalTitle').textContent = editar ? 'Editar Evidencia' : 'Registrar Nueva Evidencia';
                $('evidenciaSave').textContent = editar ? '💾 Guardar Cambios' : '💾 Guardar Evidencia';
            }

            function abrir(trigger) {
                lastFocus = trigger || document.activeElement;
                overlay.classList.add('open');
                $('eTitulo').focus();
            }

            function cerrar() {
                overlay.classList.remove('open');
                if (lastFocus) lastFocus.focus();
            }

            function limpiarErrores() {
                form.querySelectorAll('.invalid').forEach((elemento) => elemento.classList.remove('invalid'));
                form.querySelectorAll('.mf-error').forEach((elemento) => elemento.remove());
            }

            $('btnNueva').addEventListener('click', (event) => {
                form.reset();
                limpiarErrores();
                configurar('crear', {});
                rubricaExistente = false;
                $('eAsignatura').selectedIndex = 0;
                poblarGrupos($('eAsignatura').value);
                $('eTipo').value = 'reporte';
                marcarBloqueada(false);
                codigoAutomatico = '';
                cargarCriterios([]);
                $('rSelectorWrap').hidden = true;
                $('rSelector').required = false;
                mensajeRubrica('Adjunta un archivo con el formato de rúbricas o agrega los criterios manualmente.');
                abrir(event.currentTarget);
            });

            document.querySelectorAll('.btn-editar').forEach((boton) => {
                boton.addEventListener('click', () => {
                    const datos = boton.dataset;
                    form.reset();
                    limpiarErrores();
                    configurar('editar', { action: datos.action, id: datos.id });
                    rubricaExistente = JSON.parse(datos.criterios || '[]').length > 0;
                    $('eTitulo').value = datos.titulo;
                    $('eCodigo').value = datos.codigo || '';
                    codigoAutomatico = '';
                    $('eTipo').value = datos.tipo;
                    $('eFecha').value = datos.fecha;
                    $('eAsignatura').value = datos.asignatura || '';
                    if ($('eAsignatura').selectedIndex < 0) $('eAsignatura').selectedIndex = 0;
                    poblarGrupos($('eAsignatura').value, datos.grupo || '');
                    marcarBloqueada(datos.bloqueada === '1');
                    if (datos.bloqueada !== '1') cargarCriterios(JSON.parse(datos.criterios || '[]'));
                    $('rSelectorWrap').hidden = true;
                    $('rSelector').required = false;
                    mensajeRubrica('Puedes ajustar los criterios, o adjuntar un archivo para reemplazarlos.');
                    abrir(boton);
                });
            });

            $('eAsignatura').addEventListener('change', () => poblarGrupos($('eAsignatura').value));

            $('evidenciaModalClose').addEventListener('click', cerrar);
            $('evidenciaModalCancel').addEventListener('click', cerrar);
            // Clic fuera del cuadro o Escape piden confirmación si hay datos sin guardar.
            const intentarCerrar = protegerModal(overlay, form, cerrar);
            overlay.addEventListener('click', (event) => { if (event.target === overlay) intentarCerrar(); });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && overlay.classList.contains('open')) intentarCerrar();
            });

            form.addEventListener('submit', (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    form.reportValidity();
                    return;
                }
                if (!$('rubricaEditor').hidden) {
                    const filas = $('rLista').children.length;
                    if (filas === 0 || Math.abs(actualizarSuma() - PUNTAJE_TOTAL) >= 0.005) {
                        event.preventDefault();
                        mensajeRubrica(
                            filas === 0
                                ? 'La rúbrica es obligatoria: adjunta un archivo o agrega los criterios.'
                                : 'Los puntos de la rúbrica deben sumar exactamente ' + PUNTAJE_TOTAL + ' (ahora suman ' + actualizarSuma() + ').',
                            'bad'
                        );
                        $('rubricaBox').scrollIntoView({ block: 'center', behavior: 'smooth' });
                        return;
                    }
                }
                $('evidenciaSave').disabled = true;
            });

            const asignaturaPrevia = asignaturaDeGrupo(grupoPrevio);
            $('eAsignatura').value = asignaturaPrevia;
            if ($('eAsignatura').selectedIndex < 0) $('eAsignatura').selectedIndex = 0;
            poblarGrupos($('eAsignatura').value, grupoPrevio);
            if (reabrir) {
                configurar(reabrir.modo, reabrir);
                rubricaExistente = Boolean(reabrir.tieneRubrica);
                marcarBloqueada(Boolean(reabrir.bloqueada));
                if (!reabrir.bloqueada) cargarCriterios(criteriosPrevios);
                abrir(null);
            }
        })();
    </script>
@endsection
