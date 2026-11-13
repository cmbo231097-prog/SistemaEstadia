@extends('layouts.panel')

@section('title', 'Gestión de Equipos')

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

    .member-count { display:inline-block; padding:5px 11px; border-radius:999px; background:var(--ok-bg); color:var(--ok-fg); font-size:11.5px; font-weight:700; white-space:nowrap; }
    .member-row { display:flex; gap:8px; align-items:center; margin-bottom:10px; }
    .member-row select { flex:1 1 0; min-width:0; }
    .member-remove { background:#fff; border:1px solid var(--border); color:var(--bad-fg); border-radius:8px; padding:9px 12px; font-size:12px; font-weight:700; }
    .member-add { background:#fff; border:1px dashed var(--blue); color:var(--blue); border-radius:8px; padding:9px 14px; font-size:12.5px; font-weight:700; width:100%; }
    .member-add:disabled { opacity:.5; cursor:not-allowed; }
    .member-hint { font-size:11.5px; color:var(--muted); margin-top:6px; }

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
    <h1 class="page-title">Administración de Equipos de Trabajo</h1>
    <p class="page-sub">Organización de los estudiantes en equipos de {{ $min }} a {{ $max }} integrantes para la realización y evaluación de evidencias académicas</p>

    @if (session('status'))
        <div class="alert alert-ok" role="status" data-autocierre>{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('equipos.index') }}" class="panel toolbar" role="search">
        <input type="search" name="q" value="{{ $filtros['q'] }}" class="field-control search"
            placeholder="🔍 Buscar por equipo o alumno..." aria-label="Buscar por equipo o alumno">
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
        <button type="button" class="btn btn-navy" id="btnNuevo">＋ Nuevo Equipo</button>
    </form>

    <section class="panel table-card" aria-label="Listado de equipos">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Equipo</th>
                        <th scope="col">Grupo y Asignatura</th>
                        <th scope="col">Integrantes del Equipo</th>
                        <th scope="col" class="center">Conformación</th>
                        <th scope="col" class="center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipos as $equipo)
                        <tr>
                            <td><strong>{{ $equipo->nombre_equipo }}</strong></td>
                            <td>{{ $equipo->grupo?->clave_grupo }} — {{ $equipo->grupo?->asignatura?->nombre_asignatura }}</td>
                            <td>{{ $equipo->integrantes->map(fn ($i) => $i->nombre_completo)->join(', ') }}</td>
                            <td class="center"><span class="member-count">{{ $equipo->integrantes->count() }} integrantes</span></td>
                            <td class="center table-actions">
                                <button type="button" class="act-btn act-edit btn-editar"
                                    data-action="{{ route('equipos.update', $equipo) }}"
                                    data-id="{{ $equipo->getKey() }}"
                                    data-nombre="{{ $equipo->nombre_equipo }}"
                                    data-grupo="{{ $equipo->id_grupo }}"
                                    data-integrantes="{{ $equipo->integrantes->pluck('id_usuario')->join(',') }}">Editar</button>
                                <form method="POST" action="{{ route('equipos.destroy', $equipo) }}" id="del-equipo-{{ $equipo->getKey() }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                                    <input type="hidden" name="filtro_asignatura" value="{{ $filtros['asignatura'] }}">
                                    <input type="hidden" name="filtro_grupo" value="{{ $filtros['grupo'] }}">
                                    <input type="hidden" name="page" value="{{ $equipos->currentPage() }}">
                                    <button type="button" class="act-btn act-del"
                                        data-confirm
                                        data-title="Eliminar equipo"
                                        data-message="¿Deseas eliminar el equipo «{{ $equipo->nombre_equipo }}»? Sus integrantes quedarán libres para formar otro equipo."
                                        data-ok-label="Eliminar"
                                        data-submit="#del-equipo-{{ $equipo->getKey() }}">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No se encontraron equipos con los filtros aplicados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">
            <span>
                @if ($equipos->total() > 0)
                    Mostrando {{ $equipos->firstItem() }} a {{ $equipos->lastItem() }} de {{ $equipos->total() }} equipos
                @else
                    Sin equipos registrados
                @endif
            </span>
            {{ $equipos->links('partials.pagination') }}
        </div>
    </section>

    <div class="modal-overlay" id="equipoModal" role="dialog" aria-modal="true" aria-labelledby="equipoModalTitle">
        <form class="modal modal-form" method="POST" action="{{ route('equipos.store') }}" id="equipoForm" novalidate>
            @csrf
            <input type="hidden" name="_method" value="PUT" id="qMethod" disabled>
            <input type="hidden" name="_modo" value="{{ old('_modo', 'crear') }}" id="qModo">
            <input type="hidden" name="_id" value="{{ old('_id') }}" id="qId">
            <input type="hidden" name="q" value="{{ $filtros['q'] }}">
            <input type="hidden" name="filtro_asignatura" value="{{ $filtros['asignatura'] }}" id="qFiltroAsignatura" disabled>
            <input type="hidden" name="filtro_grupo" value="{{ $filtros['grupo'] }}" id="qFiltroGrupo" disabled>
            <input type="hidden" name="page" value="{{ $equipos->currentPage() }}" id="qPage" disabled>

            <div class="mf-head">
                <h3 id="equipoModalTitle">Registrar Nuevo Equipo</h3>
                <button type="button" class="mf-close" id="equipoModalClose" aria-label="Cerrar">✕</button>
            </div>
            <div class="mf-body">
                <p class="mf-intro">Conforme un equipo de trabajo colaborativo seleccionando a los alumnos de la clase</p>
                <div class="mf-grid">
                    @php $err = fn ($campo) => $errors->first($campo); @endphp
                    <div class="mf-field {{ $err('id_grupo') ? 'invalid' : '' }}">
                        <label for="qGrupo">Grupo escolar *</label>
                        <select id="qGrupo" name="id_grupo" class="field-control" required>
                            @foreach ($grupos as $grupo)
                                <option value="{{ $grupo->getKey() }}" @selected((string) old('id_grupo') === (string) $grupo->getKey())>{{ $grupo->clave_grupo }} ({{ $grupo->asignatura?->nombre_asignatura }})</option>
                            @endforeach
                        </select>
                        @if ($err('id_grupo'))<div class="mf-error">{{ $err('id_grupo') }}</div>@endif
                    </div>
                    <div class="mf-field {{ $err('nombre') ? 'invalid' : '' }}">
                        <label for="qNombre">Nombre del Equipo *</label>
                        <input type="text" id="qNombre" name="nombre" class="field-control" maxlength="80" required
                            placeholder="Ej. Equipo 6" value="{{ old('nombre') }}">
                        @if ($err('nombre'))<div class="mf-error">{{ $err('nombre') }}</div>@endif
                    </div>
                    <div class="mf-field mf-full {{ ($err('integrantes') || $errors->has('integrantes.*')) ? 'invalid' : '' }}">
                        <label>Integrantes * <span class="member-hint">(de {{ $min }} a {{ $max }})</span></label>
                        <div id="qIntegrantes"></div>
                        <button type="button" class="member-add" id="qAgregar">＋ Agregar integrante</button>
                        @if ($err('integrantes'))<div class="mf-error">{{ $err('integrantes') }}</div>@endif
                        @foreach ($errors->get('integrantes.*') as $mensajes)
                            <div class="mf-error">{{ $mensajes[0] }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="mf-foot">
                <button type="button" class="mf-cancel" id="equipoModalCancel">Cancelar</button>
                <button type="submit" class="mf-save" id="equipoSave">💾 Guardar Equipo</button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const overlay = document.getElementById('equipoModal');
            const form = document.getElementById('equipoForm');
            const $ = (id) => document.getElementById(id);
            const storeUrl = @json(route('equipos.store'));
            const reabrir = @json($reabrir);
            const MIN = @json($min);
            const MAX = @json($max);
            const estudiantes = @json($estudiantesJs);
            const ocupados = @json((object) $ocupadosJs);
            const integrantesPrevios = @json(array_values(array_map('strval', (array) old('integrantes', []))));
            const contenedor = $('qIntegrantes');
            let equipoActual = '';
            let lastFocus = null;

            function disponibles(propios) {
                const grupo = $('qGrupo').value;
                const tomados = ocupados[grupo] || {};
                return estudiantes.filter((e) => !(e.id in tomados) || String(tomados[e.id]) === String(equipoActual) || propios.includes(e.id));
            }

            function seleccionados() {
                return [...contenedor.querySelectorAll('select')].map((s) => s.value).filter(Boolean);
            }

            function actualizarOpciones() {
                const elegidos = seleccionados();
                contenedor.querySelectorAll('select').forEach((selector) => {
                    const valor = selector.value;
                    selector.replaceChildren(new Option('Seleccionar alumno', ''));
                    disponibles(valor ? [valor] : []).forEach((e) => {
                        if (e.id === valor || !elegidos.includes(e.id)) selector.add(new Option(e.texto, e.id));
                    });
                    selector.value = valor;
                });
                $('qAgregar').disabled = contenedor.children.length >= MAX;
            }

            function agregarFila(valor = '') {
                if (contenedor.children.length >= MAX) return;
                const fila = document.createElement('div');
                fila.className = 'member-row';
                const selector = document.createElement('select');
                selector.name = 'integrantes[]';
                selector.className = 'field-control';
                selector.setAttribute('aria-label', 'Integrante');
                selector.addEventListener('change', actualizarOpciones);
                fila.append(selector);
                if (contenedor.children.length >= MIN) {
                    const quitar = document.createElement('button');
                    quitar.type = 'button';
                    quitar.className = 'member-remove';
                    quitar.textContent = 'Quitar';
                    quitar.addEventListener('click', () => { fila.remove(); actualizarOpciones(); });
                    fila.append(quitar);
                }
                contenedor.append(fila);
                selector.add(new Option('Seleccionar alumno', ''));
                if (valor) selector.add(new Option((estudiantes.find((e) => e.id === valor) || {}).texto || valor, valor));
                selector.value = valor;
                actualizarOpciones();
            }

            function cargarFilas(valores) {
                contenedor.replaceChildren();
                const total = Math.max(MIN, valores.length);
                for (let i = 0; i < total; i++) agregarFila(valores[i] || '');
                actualizarOpciones();
            }

            function configurar(modo, contexto) {
                const editar = modo === 'editar';
                form.action = editar ? contexto.action : storeUrl;
                equipoActual = editar ? (contexto.id || '') : '';
                $('qMethod').disabled = !editar;
                $('qModo').value = modo;
                $('qId').value = equipoActual;
                $('qFiltroAsignatura').disabled = !editar;
                $('qFiltroGrupo').disabled = !editar;
                $('qPage').disabled = !editar;
                $('equipoModalTitle').textContent = editar ? 'Editar Equipo' : 'Registrar Nuevo Equipo';
                $('equipoSave').textContent = editar ? '💾 Guardar Cambios' : '💾 Guardar Equipo';
            }

            function abrir(trigger) {
                lastFocus = trigger || document.activeElement;
                overlay.classList.add('open');
                $('qNombre').focus();
            }

            function cerrar() {
                overlay.classList.remove('open');
                if (lastFocus) lastFocus.focus();
            }

            function limpiarErrores() {
                form.querySelectorAll('.invalid').forEach((e) => e.classList.remove('invalid'));
                form.querySelectorAll('.mf-error').forEach((e) => e.remove());
            }

            $('btnNuevo').addEventListener('click', (event) => {
                form.reset();
                limpiarErrores();
                configurar('crear', {});
                $('qGrupo').value = @json((string) ($filtros['grupo'] ?? ''));
                if ($('qGrupo').selectedIndex < 0) $('qGrupo').selectedIndex = 0;
                cargarFilas([]);
                abrir(event.currentTarget);
            });

            document.querySelectorAll('.btn-editar').forEach((boton) => {
                boton.addEventListener('click', () => {
                    const datos = boton.dataset;
                    form.reset();
                    limpiarErrores();
                    configurar('editar', { action: datos.action, id: datos.id });
                    $('qNombre').value = datos.nombre;
                    $('qGrupo').value = datos.grupo;
                    cargarFilas(datos.integrantes ? datos.integrantes.split(',') : []);
                    abrir(boton);
                });
            });

            $('qGrupo').addEventListener('change', () => {
                contenedor.querySelectorAll('select').forEach((s) => { s.value = ''; });
                actualizarOpciones();
            });
            $('qAgregar').addEventListener('click', () => agregarFila());

            $('equipoModalClose').addEventListener('click', cerrar);
            $('equipoModalCancel').addEventListener('click', cerrar);
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
                if (seleccionados().length < MIN) {
                    event.preventDefault();
                    alert('Selecciona al menos ' + MIN + ' integrantes.');
                    return;
                }
                $('equipoSave').disabled = true;
            });

            cargarFilas(integrantesPrevios);
            if (reabrir) {
                configurar(reabrir.modo, reabrir);
                abrir(null);
            }
        })();
    </script>
@endsection
