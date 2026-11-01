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

    .modal.modal-form { max-width:560px; padding:0; overflow:hidden; max-height:calc(100vh - 32px); display:flex; flex-direction:column; }
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
    <p class="page-sub">Parametrización de actividades académicas, especificación de entregables y asignación por grupos de clase</p>

    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
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
                        <th scope="col">Entregables Requeridos</th>
                        <th scope="col">Fecha Límite</th>
                        <th scope="col" class="center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($evidencias as $evidencia)
                        @php $grupoEv = $evidencia->grupos->first(); @endphp
                        <tr>
                            <td class="ev-name">{{ $evidencia->titulo }}</td>
                            <td>{{ $grupoEv?->asignatura?->nombre_asignatura ?? '—' }}</td>
                            <td>{{ $evidencia->grupos->pluck('clave_grupo')->join(', ') ?: '—' }}</td>
                            <td class="center"><span class="tipo-badge">{{ $tipos[$evidencia->tipo_evidencia] ?? $evidencia->tipo_evidencia }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit($evidencia->descripcion_entregables, 60) }}</td>
                            <td>{{ $evidencia->fecha_limite?->format('d/m/Y H:i') }}</td>
                            <td class="center table-actions">
                                <button type="button" class="act-btn act-edit btn-editar"
                                    data-action="{{ route('evidencias.update', $evidencia) }}"
                                    data-id="{{ $evidencia->getKey() }}"
                                    data-titulo="{{ $evidencia->titulo }}"
                                    data-tipo="{{ $evidencia->tipo_evidencia }}"
                                    data-nivel="{{ $evidencia->nivel_retroalimentacion_ia }}"
                                    data-entregables="{{ $evidencia->descripcion_entregables }}"
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
                <p class="mf-intro">Parametrice la actividad académica, el grupo destinatario y los componentes específicos de entrega</p>
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
                            <option value="">Selecciona una asignatura</option>
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
                    <div class="mf-field {{ $err('tipo_evidencia') ? 'invalid' : '' }}">
                        <label for="eTipo">Tipo de Evidencia *</label>
                        <select id="eTipo" name="tipo_evidencia" class="field-control" required>
                            @foreach ($tipos as $clave => $nombre)
                                <option value="{{ $clave }}" @selected(old('tipo_evidencia', 'reporte') === $clave)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                        @if ($err('tipo_evidencia'))<div class="mf-error">{{ $err('tipo_evidencia') }}</div>@endif
                    </div>
                    <div class="mf-field {{ $err('nivel_retroalimentacion_ia') ? 'invalid' : '' }}">
                        <label for="eNivel">Retroalimentación de la IA *</label>
                        <select id="eNivel" name="nivel_retroalimentacion_ia" class="field-control" required>
                            @foreach ($niveles as $clave => $nombre)
                                <option value="{{ $clave }}" @selected(old('nivel_retroalimentacion_ia', 'exhaustiva') === $clave)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                        @if ($err('nivel_retroalimentacion_ia'))<div class="mf-error">{{ $err('nivel_retroalimentacion_ia') }}</div>@endif
                    </div>
                    <div class="mf-field mf-full {{ $err('fecha_limite') ? 'invalid' : '' }}">
                        <label for="eFecha">Fecha Límite *</label>
                        <input type="datetime-local" id="eFecha" name="fecha_limite" class="field-control" required value="{{ old('fecha_limite') }}">
                        @if ($err('fecha_limite'))<div class="mf-error">{{ $err('fecha_limite') }}</div>@endif
                    </div>
                    <div class="mf-field mf-full {{ $err('entregables') ? 'invalid' : '' }}">
                        <label for="eEntregables">Entregables requeridos dentro del documento *</label>
                        <textarea id="eEntregables" name="entregables" class="field-control" maxlength="2000" required
                            placeholder="Ej. Introducción teórica, código fuente documentado, diagrama de clases UML y conclusiones">{{ old('entregables') }}</textarea>
                        @if ($err('entregables'))<div class="mf-error">{{ $err('entregables') }}</div>@endif
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
            let lastFocus = null;

            function poblarGrupos(asignatura, seleccionado = '') {
                const selector = $('eGrupo');
                selector.replaceChildren(new Option('Selecciona un grupo', ''));
                grupos.filter((g) => g.asignatura === String(asignatura)).forEach((g) => selector.add(new Option(g.clave, g.id)));
                selector.value = seleccionado;
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
                $('eAsignatura').value = '';
                poblarGrupos('');
                $('eTipo').value = 'reporte';
                $('eNivel').value = 'exhaustiva';
                abrir(event.currentTarget);
            });

            document.querySelectorAll('.btn-editar').forEach((boton) => {
                boton.addEventListener('click', () => {
                    const datos = boton.dataset;
                    form.reset();
                    limpiarErrores();
                    configurar('editar', { action: datos.action, id: datos.id });
                    $('eTitulo').value = datos.titulo;
                    $('eTipo').value = datos.tipo;
                    $('eNivel').value = datos.nivel || 'exhaustiva';
                    $('eEntregables').value = datos.entregables;
                    $('eFecha').value = datos.fecha;
                    $('eAsignatura').value = datos.asignatura || '';
                    poblarGrupos(datos.asignatura || '', datos.grupo || '');
                    abrir(boton);
                });
            });

            $('eAsignatura').addEventListener('change', () => poblarGrupos($('eAsignatura').value));

            $('evidenciaModalClose').addEventListener('click', cerrar);
            $('evidenciaModalCancel').addEventListener('click', cerrar);
            overlay.addEventListener('click', (event) => { if (event.target === overlay) cerrar(); });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && overlay.classList.contains('open')) cerrar();
            });

            form.addEventListener('submit', (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    form.reportValidity();
                    return;
                }
                $('evidenciaSave').disabled = true;
            });

            const asignaturaPrevia = asignaturaDeGrupo(grupoPrevio);
            $('eAsignatura').value = asignaturaPrevia;
            poblarGrupos(asignaturaPrevia, grupoPrevio);
            if (reabrir) {
                configurar(reabrir.modo, reabrir);
                abrir(null);
            }
        })();
    </script>
@endsection
