@extends('layouts.panel')

@section('title', 'Gestión de Asignaturas')

@section('styles')
    @include('partials.dashboard-styles')

    /* Estilos propios de la búsqueda, tabla y formulario de asignaturas. */
    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }

    .toolbar { display:flex; flex-wrap:wrap; gap:12px; padding:16px 18px; margin-bottom:18px; align-items:center; }
    .toolbar .search { flex:1 1 230px; }
    .field-control {
        width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text);
    }
    .toolbar select { flex:0 1 190px; width:auto; padding-right:42px; }
    .toolbar .btn { flex:0 0 auto; min-width:170px; }

    .table-card { overflow:hidden; margin-bottom:14px; }
    .table-card .table-wrap { border:0; border-radius:0; margin:0; }
    .table-foot {
        display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px;
        padding:12px 16px; border-top:1px solid var(--border); font-size:12px; color:var(--muted);
    }
    .group-count { display:inline-block; min-width:90px; padding:6px 12px; border-radius:999px; background:var(--info-bg); color:var(--info-fg); font-size:11.5px; font-weight:700; }
    .table-actions { white-space:nowrap; }
    .table-actions form { display:inline; }
    .act-btn { background:none; border:1px solid transparent; font-size:12px; font-weight:700; padding:6px 8px; border-radius:6px; }
    .act-edit { color:var(--blue); }
    .act-edit:hover { background:var(--info-bg); }
    .act-del { color:var(--bad-fg); }
    .act-del:hover { background:var(--bad-bg); }
    .footnote { font-size:12px; color:var(--muted); }

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
    .mf-info { background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:10px 12px; font-size:11.5px; color:var(--muted); }
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
    <h1 class="page-title">Catálogo de Asignaturas</h1>
    <p class="page-sub">Administración del plan de estudios institucional, claves curriculares y materias para vinculación de grupos</p>

    {{-- Mensajes de resultado de las operaciones de alta, edición y eliminación. --}}
    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif

    {{-- Búsqueda y filtros del catálogo; el cambio de modelo reinicia el programa seleccionado. --}}
    <form method="GET" action="{{ route('admin.asignaturas.index') }}" class="panel toolbar" role="search">
        <input type="search" name="q" value="{{ $filtros['q'] }}" class="field-control search"
            placeholder="🔍 Buscar por clave o nombre de materia..." aria-label="Buscar por clave o nombre de materia">
        <select name="modelo_educativo" class="field-control" aria-label="Filtrar por modelo educativo"
            onchange="this.form.elements.programa.value=''; this.form.submit()">
            <option value="">Todos los modelos</option>
            @foreach ($modelos as $claveModelo => $nombreModelo)
                <option value="{{ $claveModelo }}" @selected($filtros['modelo'] === $claveModelo)>{{ $nombreModelo }}</option>
            @endforeach
        </select>
        <select name="programa" class="field-control" aria-label="Filtrar por programa educativo" onchange="this.form.submit()">
            <option value="">Todas las carreras</option>
            @foreach ($programas as $claveModelo => $opcionesPrograma)
                @if ($filtros['modelo'] === '' || $filtros['modelo'] === $claveModelo)
                    @foreach ($opcionesPrograma as $abreviatura => $programa)
                        <option value="{{ $claveModelo }}|{{ $abreviatura }}"
                            @selected($filtros['programa'] === $claveModelo.'|'.$abreviatura)>
                            {{ $abreviatura === 'LAG' ? 'LAG / LAE' : $abreviatura }} — {{ $programa }} ({{ $modelos[$claveModelo] }})
                        </option>
                    @endforeach
                @endif
            @endforeach
        </select>
        <select name="cuatrimestre" class="field-control" aria-label="Filtrar por cuatrimestre" onchange="this.form.submit()">
            <option value="">Todos los cuatrimestres</option>
            @foreach ($cuatrimestres as $numero)
                <option value="{{ $numero }}" @selected((string) $filtros['cuatrimestre'] === (string) $numero)>{{ $numero }}° Cuatrimestre</option>
            @endforeach
        </select>
        <button type="button" class="btn btn-navy" id="btnNueva">＋ Nueva Asignatura</button>
    </form>

    {{-- Tabla paginada con el conteo de grupos y acciones disponibles por asignatura. --}}
    <section class="panel table-card" aria-label="Catálogo de asignaturas">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Clave</th>
                        <th scope="col">Modelo Educativo</th>
                        <th scope="col">Nombre de la Asignatura</th>
                        <th scope="col">Programa Educativo</th>
                        <th scope="col" class="center">Cuatrimestre</th>
                        <th scope="col" class="center">Grupos</th>
                        <th scope="col" class="center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($asignaturas as $asignatura)
                        <tr>
                            <td><strong>{{ $asignatura->clave_asignatura }}</strong></td>
                            <td>{{ $modelos[$asignatura->modelo_educativo] ?? $asignatura->modelo_educativo }}</td>
                            <td class="ev-name">
                                {{ $asignatura->nombre_asignatura }}
                            </td>
                            <td>{{ $asignatura->programa_educativo }}</td>
                            <td class="center">{{ $asignatura->cuatrimestre_etiqueta }}</td>
                            <td class="center"><span class="group-count">{{ $asignatura->grupos_count }} {{ $asignatura->grupos_count === 1 ? 'Grupo' : 'Grupos' }}</span></td>
                            <td class="center table-actions">
                                <button type="button" class="act-btn act-edit btn-editar"
                                    data-action="{{ route('admin.asignaturas.update', $asignatura) }}"
                                    data-id="{{ $asignatura->getKey() }}"
                                    data-clave="{{ $asignatura->clave_asignatura }}"
                                    data-modelo="{{ $asignatura->modelo_educativo }}"
                                    data-nombre="{{ $asignatura->nombre_asignatura }}"
                                    data-programa="{{ $asignatura->programa_educativo }}"
                                    data-cuatrimestre="{{ $asignatura->cuatrimestre }}">Editar</button>
                                <form method="POST" action="{{ route('admin.asignaturas.destroy', $asignatura) }}" id="del-asignatura-{{ $asignatura->getKey() }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                                    <input type="hidden" name="filtro_modelo" value="{{ $filtros['modelo'] }}">
                                    <input type="hidden" name="filtro_programa" value="{{ $filtros['programa'] }}">
                                    <input type="hidden" name="filtro_cuatrimestre" value="{{ $filtros['cuatrimestre'] }}">
                                    <input type="hidden" name="page" value="{{ $asignaturas->currentPage() }}">
                                    <button type="button" class="act-btn act-del"
                                        data-confirm
                                        data-title="Eliminar asignatura"
                                        data-message="¿Deseas eliminar «{{ $asignatura->nombre_asignatura }}» ({{ $asignatura->clave_asignatura }})? La operación solo será posible si no tiene grupos ni evidencias vinculados."
                                        data-ok-label="Eliminar"
                                        data-submit="#del-asignatura-{{ $asignatura->getKey() }}">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">No se encontraron asignaturas con los filtros aplicados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">
            <span>
                @if ($asignaturas->total() > 0)
                    Mostrando {{ $asignaturas->firstItem() }} a {{ $asignaturas->lastItem() }} de {{ $asignaturas->total() }} asignaturas registradas
                @else
                    Sin asignaturas registradas
                @endif
            </span>
            {{ $asignaturas->links('partials.pagination') }}
        </div>
    </section>

    <p class="footnote">ℹ️ Las asignaturas con grupos o evidencias vinculados no pueden eliminarse para conservar la integridad académica.</p>

    {{-- El mismo modal se reutiliza para registrar y editar; el modo define la ruta del formulario. --}}
    <div class="modal-overlay" id="asignaturaModal" role="dialog" aria-modal="true" aria-labelledby="asignaturaModalTitle">
        <form class="modal modal-form" method="POST" action="{{ route('admin.asignaturas.store') }}" id="asignaturaForm" novalidate>
            @csrf
            <input type="hidden" name="_method" value="PUT" id="aMethod" disabled>
            <input type="hidden" name="_modo" value="{{ old('_modo', 'crear') }}" id="aModo">
            <input type="hidden" name="_id" value="{{ old('_id') }}" id="aId">
            <input type="hidden" name="q" value="{{ $filtros['q'] }}">
            <input type="hidden" name="filtro_modelo" value="{{ $filtros['modelo'] }}" id="aFiltroModelo" disabled>
            <input type="hidden" name="filtro_programa" value="{{ $filtros['programa'] }}" id="aFiltroPrograma" disabled>
            <input type="hidden" name="filtro_cuatrimestre" value="{{ $filtros['cuatrimestre'] }}" id="aFiltroCuatrimestre" disabled>
            <input type="hidden" name="page" value="{{ $asignaturas->currentPage() }}" id="aPage" disabled>

            <div class="mf-head">
                <h3 id="asignaturaModalTitle">Registrar Nueva Asignatura</h3>
                <button type="button" class="mf-close" id="asignaturaModalClose" aria-label="Cerrar">✕</button>
            </div>
            <div class="mf-body">
                <p class="mf-intro" id="asignaturaIntro">Complete los datos para incorporar la materia al catálogo y habilitarla en la asignación de grupos</p>
                <div class="mf-grid">
                    @php $err = fn ($campo) => $errors->first($campo); @endphp
                    <div class="mf-field {{ $err('cuatrimestre') ? 'invalid' : '' }}">
                        <label for="aCuatrimestre">Cuatrimestre *</label>
                        <select id="aCuatrimestre" name="cuatrimestre" class="field-control" required>
                            @foreach ($cuatrimestres as $numero)
                                <option value="{{ $numero }}" @selected((string) old('cuatrimestre', '1') === (string) $numero)>{{ $numero }}° Cuatrimestre</option>
                            @endforeach
                        </select>
                        @if ($err('cuatrimestre'))<div class="mf-error">{{ $err('cuatrimestre') }}</div>@endif
                    </div>
                    <div class="mf-field {{ $err('modelo_educativo') ? 'invalid' : '' }}">
                        <label for="aModelo">Modelo Educativo *</label>
                        <select id="aModelo" name="modelo_educativo" class="field-control" required>
                            <option value="">Selecciona un modelo</option>
                            @foreach ($modelos as $claveModelo => $nombreModelo)
                                <option value="{{ $claveModelo }}" @selected(old('modelo_educativo', 'actual') === $claveModelo)>{{ $nombreModelo }}</option>
                            @endforeach
                        </select>
                        @if ($err('modelo_educativo'))<div class="mf-error">{{ $err('modelo_educativo') }}</div>@endif
                    </div>
                    <div class="mf-field">
                        <label>Clave de la Asignatura</label>
                        <div class="field-control" id="aClavePreview" aria-live="polite">Se genera automáticamente al guardar</div>
                    </div>
                    <div class="mf-field mf-full {{ $err('nombre') ? 'invalid' : '' }}">
                        <label for="aNombre">Nombre de la Asignatura *</label>
                        <input type="text" id="aNombre" name="nombre" class="field-control" maxlength="120" required
                            placeholder="Ej. Procesamiento de Lenguaje Natural" value="{{ old('nombre') }}">
                        @if ($err('nombre'))<div class="mf-error">{{ $err('nombre') }}</div>@endif
                    </div>
                    <div class="mf-field mf-full {{ $err('programa_educativo') ? 'invalid' : '' }}">
                        <label for="aPrograma">Programa Educativo (Carrera) *</label>
                        <select id="aPrograma" name="programa_educativo" class="field-control" required>
                            <option value="">Selecciona una carrera</option>
                        </select>
                        @if ($err('programa_educativo'))<div class="mf-error">{{ $err('programa_educativo') }}</div>@endif
                    </div>
                    <div class="mf-full mf-info">ℹ️ Las carreras disponibles dependen del modelo educativo seleccionado. La clave se genera con su abreviatura y el consecutivo interno.</div>
                </div>
            </div>
            <div class="mf-foot">
                <button type="button" class="mf-cancel" id="asignaturaModalCancel">Cancelar</button>
                <button type="submit" class="mf-save" id="asignaturaSave">💾 Guardar Asignatura</button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            // El formulario comparte catálogo de programas y estado entre sus modos crear y editar.
            const overlay = document.getElementById('asignaturaModal');
            const form = document.getElementById('asignaturaForm');
            const $ = (id) => document.getElementById(id);
            const storeUrl = @json(route('admin.asignaturas.store'));
            const reabrir = @json($reabrir);
            const programasPorModelo = @json($programas);
            const modeloPrevio = @json(old('modelo_educativo', 'actual'));
            const programaPrevio = @json(old('programa_educativo', ''));
            let lastFocus = null;

            // Reconstruye las opciones de carrera para que correspondan al modelo seleccionado.
            function poblarProgramas(modelo, seleccionado = '') {
                const selector = $('aPrograma');
                selector.replaceChildren(new Option('Selecciona una carrera', ''));
                Object.entries(programasPorModelo[modelo] || {}).forEach(([abreviatura, programa]) => {
                    const etiqueta = abreviatura === 'LAG' ? 'LAG / LAE' : abreviatura;
                    selector.add(new Option(`${etiqueta} — ${programa}`, programa));
                });
                selector.value = seleccionado;
            }

            // Configura la acción y los campos ocultos del formulario según la operación elegida.
            function configurar(modo, contexto) {
                const editar = modo === 'editar';
                form.action = editar ? contexto.action : storeUrl;
                $('aMethod').disabled = !editar;
                $('aModo').value = modo;
                $('aId').value = editar ? (contexto.id || '') : '';
                $('aFiltroModelo').disabled = !editar;
                $('aFiltroPrograma').disabled = !editar;
                $('aFiltroCuatrimestre').disabled = !editar;
                $('aPage').disabled = !editar;
                $('asignaturaModalTitle').textContent = editar ? 'Editar Asignatura' : 'Registrar Nueva Asignatura';
                $('asignaturaIntro').textContent = editar
                    ? 'Modifique los datos de la asignatura institucional'
                    : 'Complete los datos para incorporar la materia al catálogo y habilitarla en la asignación de grupos';
                $('asignaturaSave').textContent = editar ? '💾 Guardar Cambios' : '💾 Guardar Asignatura';
            }

            // Administra la apertura del modal y devuelve el foco al control que lo abrió.
            function abrir(trigger) {
                lastFocus = trigger || document.activeElement;
                overlay.classList.add('open');
                $('aCuatrimestre').focus();
            }

            function cerrar() {
                overlay.classList.remove('open');
                if (lastFocus) lastFocus.focus();
            }

            function limpiarErrores() {
                form.querySelectorAll('.invalid').forEach((elemento) => elemento.classList.remove('invalid'));
                form.querySelectorAll('.mf-error').forEach((elemento) => elemento.remove());
            }

            // Restablece el formulario y sus valores iniciales para crear una nueva asignatura.
            document.getElementById('btnNueva').addEventListener('click', (event) => {
                form.reset();
                limpiarErrores();
                configurar('crear', {});
                $('aModelo').value = 'actual';
                poblarProgramas('actual');
                $('aClavePreview').textContent = 'Se genera automáticamente al guardar';
                $('aCuatrimestre').value = '1';
                abrir(event.currentTarget);
            });

            // Carga en el modal los atributos de la fila seleccionada para editarla.
            document.querySelectorAll('.btn-editar').forEach((boton) => {
                boton.addEventListener('click', () => {
                    const datos = boton.dataset;
                    form.reset();
                    limpiarErrores();
                    configurar('editar', { action: datos.action, id: datos.id });
                    $('aClavePreview').textContent = datos.clave;
                    $('aNombre').value = datos.nombre;
                    $('aModelo').value = datos.modelo;
                    poblarProgramas(datos.modelo, datos.programa);
                    $('aCuatrimestre').value = datos.cuatrimestre;
                    abrir(boton);
                });

            });

            // Cambiar el modelo o el programa invalida la vista previa de la clave calculada por el servidor.
            $('aModelo').addEventListener('change', () => {
                poblarProgramas($('aModelo').value);
                $('aClavePreview').textContent = 'Se genera automáticamente al guardar';
            });
            $('aPrograma').addEventListener('change', () => {
                $('aClavePreview').textContent = 'Se genera automáticamente al guardar';
            });

            // Permite cerrar el diálogo con sus controles, al hacer clic fuera o con Escape.
            $('asignaturaModalClose').addEventListener('click', cerrar);
            $('asignaturaModalCancel').addEventListener('click', cerrar);
            overlay.addEventListener('click', (event) => { if (event.target === overlay) cerrar(); });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && overlay.classList.contains('open')) cerrar();
            });

            // Evita envíos inválidos y bloquea el botón para prevenir envíos duplicados.
            form.addEventListener('submit', (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    form.reportValidity();
                    return;
                }
                $('asignaturaSave').disabled = true;
            });

            // Recupera el estado del formulario después de que el servidor devuelve errores.
            poblarProgramas(modeloPrevio, programaPrevio);
            if (reabrir) {
                configurar(reabrir.modo, reabrir);
                abrir(null);
            }
        })();
    </script>
@endsection
