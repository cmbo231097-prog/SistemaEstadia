@extends('layouts.panel')

@section('title', 'Gestión de Grupos')

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
    @include('partials.lider-sidebar')
@endsection

@section('content')
    <h1 class="page-title">Gestión de Grupos</h1>
    <p class="page-sub">Vinculación de asignaturas con su docente titular y cuatrimestre</p>

    @if (session('status'))
        <div class="alert alert-ok" role="status" data-autocierre>{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.grupos.index') }}" class="panel toolbar" role="search">
        <input type="search" name="q" value="{{ $filtros['q'] }}" class="field-control search"
            placeholder="🔍 Buscar por grupo, asignatura o docente..." aria-label="Buscar por grupo, asignatura o docente">
        <select name="docente" class="field-control" aria-label="Filtrar por docente" onchange="this.form.submit()">
            <option value="">Todos los docentes</option>
            @foreach ($docentes as $docente)
                <option value="{{ $docente->getKey() }}" @selected($filtros['docente'] === (string) $docente->getKey())>{{ $docente->nombre_completo }}</option>
            @endforeach
        </select>
        <select name="asignatura" class="field-control" aria-label="Filtrar por asignatura" onchange="this.form.submit()">
            <option value="">Todas las asignaturas</option>
            @foreach ($asignaturas as $asignatura)
                <option value="{{ $asignatura->getKey() }}" @selected($filtros['asignatura'] === (string) $asignatura->getKey())>{{ $asignatura->nombre_asignatura }}</option>
            @endforeach
        </select>
        <button type="button" class="btn btn-navy" id="btnNuevo">＋ Nuevo Grupo</button>
    </form>

    <section class="panel table-card" aria-label="Listado de grupos">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Grupo</th>
                        <th scope="col">Asignatura</th>
                        <th scope="col" class="center">Cuatrimestre</th>
                        <th scope="col">Docente Titular</th>
                        <th scope="col">Periodo</th>
                        <th scope="col" class="center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grupos as $grupo)
                        <tr>
                            <td><strong>{{ $grupo->clave_grupo }}</strong></td>
                            <td class="ev-name">
                                {{ $grupo->asignatura?->nombre_asignatura }}
                                <small>{{ $grupo->asignatura?->clave_asignatura }}</small>
                            </td>
                            <td class="center">{{ $grupo->asignatura?->cuatrimestre_etiqueta }}</td>
                            <td>{{ $grupo->docente?->nombre_completo }}</td>
                            <td>{{ $grupo->periodo?->nombre_periodo }}</td>
                            <td class="center table-actions">
                                <button type="button" class="act-btn act-edit btn-editar"
                                    data-action="{{ route('admin.grupos.update', $grupo) }}"
                                    data-id="{{ $grupo->getKey() }}"
                                    data-identificador="{{ $grupo->clave_grupo }}"
                                    data-asignatura="{{ $grupo->id_asignatura }}"
                                    data-docente="{{ $grupo->id_docente }}"
                                    data-periodo="{{ $grupo->id_periodo }}">Editar</button>
                                <form method="POST" action="{{ route('admin.grupos.destroy', $grupo) }}" id="del-grupo-{{ $grupo->getKey() }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                                    <input type="hidden" name="filtro_docente" value="{{ $filtros['docente'] }}">
                                    <input type="hidden" name="filtro_asignatura" value="{{ $filtros['asignatura'] }}">
                                    <input type="hidden" name="page" value="{{ $grupos->currentPage() }}">
                                    <button type="button" class="act-btn act-del"
                                        data-confirm
                                        data-title="Eliminar grupo"
                                        data-message="¿Deseas eliminar el grupo {{ $grupo->clave_grupo }} de «{{ $grupo->asignatura?->nombre_asignatura }}»? Solo será posible si no tiene equipos ni evidencias vinculados."
                                        data-ok-label="Eliminar"
                                        data-submit="#del-grupo-{{ $grupo->getKey() }}">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">No se encontraron grupos con los filtros aplicados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">
            <span>
                @if ($grupos->total() > 0)
                    Mostrando {{ $grupos->firstItem() }} a {{ $grupos->lastItem() }} de {{ $grupos->total() }} grupos registrados
                @else
                    Sin grupos registrados
                @endif
            </span>
            {{ $grupos->links('partials.pagination') }}
        </div>
    </section>

    <div class="modal-overlay" id="grupoModal" role="dialog" aria-modal="true" aria-labelledby="grupoModalTitle">
        <form class="modal modal-form" method="POST" action="{{ route('admin.grupos.store') }}" id="grupoForm" novalidate>
            @csrf
            <input type="hidden" name="_method" value="PUT" id="gMethod" disabled>
            <input type="hidden" name="_modo" value="{{ old('_modo', 'crear') }}" id="gModo">
            <input type="hidden" name="_id" value="{{ old('_id') }}" id="gId">
            <input type="hidden" name="q" value="{{ $filtros['q'] }}">
            <input type="hidden" name="filtro_docente" value="{{ $filtros['docente'] }}" id="gFiltroDocente" disabled>
            <input type="hidden" name="filtro_asignatura" value="{{ $filtros['asignatura'] }}" id="gFiltroAsignatura" disabled>
            <input type="hidden" name="page" value="{{ $grupos->currentPage() }}" id="gPage" disabled>

            <div class="mf-head">
                <h3 id="grupoModalTitle">Registrar Nuevo Grupo</h3>
                <button type="button" class="mf-close" id="grupoModalClose" aria-label="Cerrar">✕</button>
            </div>
            <div class="mf-body">
                <p class="mf-intro" id="grupoIntro">Vincule una asignatura con su docente titular y cuatrimestre</p>
                <div class="mf-grid">
                    @php $err = fn ($campo) => $errors->first($campo); @endphp
                    <div class="mf-field {{ $err('identificador') ? 'invalid' : '' }}">
                        <label for="gIdentificador">Identificador del Grupo *</label>
                        <input type="text" id="gIdentificador" name="identificador" class="field-control" maxlength="20" required
                            placeholder="Ej. 9A" value="{{ old('identificador') }}">
                        @if ($err('identificador'))<div class="mf-error">{{ $err('identificador') }}</div>@endif
                    </div>
                    <div class="mf-field {{ $err('id_periodo') ? 'invalid' : '' }}">
                        <label for="gPeriodo">Periodo Escolar *</label>
                        <select id="gPeriodo" name="id_periodo" class="field-control" required>
                            @foreach ($periodos as $periodo)
                                <option value="{{ $periodo->getKey() }}" @selected((string) old('id_periodo', $periodoPorDefecto) === (string) $periodo->getKey())>{{ $periodo->nombre_periodo }}</option>
                            @endforeach
                        </select>
                        @if ($err('id_periodo'))<div class="mf-error">{{ $err('id_periodo') }}</div>@endif
                    </div>
                    <div class="mf-field mf-full {{ $err('id_asignatura') ? 'invalid' : '' }}">
                        <label for="gAsignatura">Asignatura *</label>
                        <select id="gAsignatura" name="id_asignatura" class="field-control" required>
                            @foreach ($asignaturas as $asignatura)
                                <option value="{{ $asignatura->getKey() }}" @selected((string) old('id_asignatura') === (string) $asignatura->getKey())>
                                    {{ $asignatura->clave_asignatura }} — {{ $asignatura->nombre_asignatura }}
                                </option>
                            @endforeach
                        </select>
                        @if ($err('id_asignatura'))<div class="mf-error">{{ $err('id_asignatura') }}</div>@endif
                    </div>
                    <div class="mf-field mf-full {{ $err('id_docente') ? 'invalid' : '' }}">
                        <label for="gDocente">Docente Titular *</label>
                        <select id="gDocente" name="id_docente" class="field-control" required>
                            @foreach ($docentes as $docente)
                                <option value="{{ $docente->getKey() }}" @selected((string) old('id_docente') === (string) $docente->getKey())>{{ $docente->nombre_completo }}</option>
                            @endforeach
                        </select>
                        @if ($err('id_docente'))<div class="mf-error">{{ $err('id_docente') }}</div>@endif
                    </div>
                </div>
            </div>
            <div class="mf-foot">
                <button type="button" class="mf-cancel" id="grupoModalCancel">Cancelar</button>
                <button type="submit" class="mf-save" id="grupoSave">💾 Guardar Grupo</button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const overlay = document.getElementById('grupoModal');
            const form = document.getElementById('grupoForm');
            const $ = (id) => document.getElementById(id);
            const storeUrl = @json(route('admin.grupos.store'));
            const reabrir = @json($reabrir);
            const periodoPorDefecto = @json((string) $periodoPorDefecto);
            let lastFocus = null;

            function configurar(modo, contexto) {
                const editar = modo === 'editar';
                form.action = editar ? contexto.action : storeUrl;
                $('gMethod').disabled = !editar;
                $('gModo').value = modo;
                $('gId').value = editar ? (contexto.id || '') : '';
                $('gFiltroDocente').disabled = !editar;
                $('gFiltroAsignatura').disabled = !editar;
                $('gPage').disabled = !editar;
                $('grupoModalTitle').textContent = editar ? 'Editar Grupo' : 'Registrar Nuevo Grupo';
                $('grupoSave').textContent = editar ? '💾 Guardar Cambios' : '💾 Guardar Grupo';
            }

            function abrir(trigger) {
                lastFocus = trigger || document.activeElement;
                overlay.classList.add('open');
                $('gIdentificador').focus();
            }

            function cerrar() {
                overlay.classList.remove('open');
                if (lastFocus) lastFocus.focus();
            }

            function limpiarErrores() {
                form.querySelectorAll('.invalid').forEach((elemento) => elemento.classList.remove('invalid'));
                form.querySelectorAll('.mf-error').forEach((elemento) => elemento.remove());
            }

            $('btnNuevo').addEventListener('click', (event) => {
                form.reset();
                limpiarErrores();
                configurar('crear', {});
                $('gPeriodo').value = periodoPorDefecto;
                abrir(event.currentTarget);
            });

            document.querySelectorAll('.btn-editar').forEach((boton) => {
                boton.addEventListener('click', () => {
                    const datos = boton.dataset;
                    form.reset();
                    limpiarErrores();
                    configurar('editar', { action: datos.action, id: datos.id });
                    $('gIdentificador').value = datos.identificador;
                    $('gPeriodo').value = datos.periodo;
                    $('gAsignatura').value = datos.asignatura;
                    $('gDocente').value = datos.docente;
                    abrir(boton);
                });
            });

            $('grupoModalClose').addEventListener('click', cerrar);
            $('grupoModalCancel').addEventListener('click', cerrar);
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
                $('grupoSave').disabled = true;
            });

            if (reabrir) {
                configurar(reabrir.modo, reabrir);
                abrir(null);
            }
        })();
    </script>
@endsection
