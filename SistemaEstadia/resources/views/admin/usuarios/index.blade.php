@extends('layouts.panel')

@section('title', 'Gestión de Usuarios')

@section('styles')
    @include('partials.dashboard-styles')

    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }

    .toolbar { display:flex; flex-wrap:wrap; gap:12px; padding:16px 18px; margin-bottom:18px; align-items:center; }
    .toolbar .search { flex:1 1 240px; }
    .field-control {
        width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text);
    }
    .toolbar select { flex:0 1 190px; width:auto; }
    select.field-control { padding-right:42px; }
    .toolbar .btn { flex:0 0 auto; min-width:170px; }

    .table-card { overflow:hidden; margin-bottom:14px; }
    .table-card .table-wrap { border:0; border-radius:0; margin:0; }
    .table-foot {
        display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px;
        padding:12px 16px; border-top:1px solid var(--border); font-size:12px; color:var(--muted);
    }

    .role-pill { display:inline-block; min-width:118px; padding:6px 14px; border-radius:999px; font-size:11.5px; font-weight:700; text-align:center; }
    .role-lider { background:#ede9fe; color:#6d28d9; }
    .role-profesor { background:var(--info-bg); color:var(--info-fg); }
    .role-estudiante { background:var(--idle-bg); color:#475569; }

    .estado-btn { border:0; cursor:pointer; font-family:inherit; transition:filter .15s, box-shadow .15s; }
    .estado-btn:hover { filter:brightness(.95); box-shadow:0 0 0 2px rgba(30,58,110,.25); }
    .estado-btn:focus-visible { outline:3px solid #93b4ff; outline-offset:2px; }

    .act-btn { background:none; border:1px solid transparent; font-size:12px; font-weight:700; padding:6px 10px; border-radius:6px; }
    .act-edit { color:var(--blue); }
    .act-edit:hover { background:var(--info-bg); }
    .act-del { color:var(--bad-fg); }
    .act-del:hover:not(:disabled) { background:var(--bad-bg); }
    .act-btn:disabled { color:#94a3b8; cursor:not-allowed; }

    
    /* Formulario emergente */
    .modal.modal-form { max-width:620px; padding:0; overflow:hidden; max-height:calc(100vh - 32px); display:flex; flex-direction:column; }
    .mf-head { background:var(--navy); color:#fff; display:flex; justify-content:space-between; align-items:center; padding:14px 20px; }
    .mf-head h3 { margin:0; font-size:15px; }
    .mf-close { background:none; border:0; color:#fff; font-size:20px; line-height:1; padding:4px 8px; }
    .mf-body { padding:18px 22px; overflow-y:auto; }
    .mf-intro { margin:0 0 14px !important; font-size:12px !important; color:var(--muted) !important; }
    .mf-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 16px; }
    .mf-full { grid-column:1 / -1; }
    .mf-field label { display:block; font-size:12px; font-weight:700; margin-bottom:5px; }
    .mf-field .opt { font-weight:400; color:var(--muted); }
    .mf-field.invalid .field-control { border-color:#dc2626; }
    .mf-error { color:#b91c1c; font-size:11.5px; margin-top:4px; }
    .input-ico { position:relative; }
    .input-ico .ico { position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:13px; pointer-events:none; }
    .input-ico .field-control { padding-left:32px; }
    .mf-foot { display:flex; justify-content:flex-end; gap:10px; padding:14px 22px; border-top:1px solid var(--border); background:#fff; flex-wrap:wrap; }
    .mf-foot button { border-radius:8px; padding:10px 22px; font-size:13px; font-weight:700; border:1px solid var(--border); }
    .mf-cancel { background:#fff; color:#334155; }
    .mf-save { background:var(--navy); color:#fff; border-color:var(--navy) !important; }
    .mf-save:hover { filter:brightness(1.1); }
    [hidden] { display:none !important; }
    #fMatricula::placeholder { text-transform:none; }

    @media (max-width: 560px) {
        .mf-grid { grid-template-columns:1fr; }
        .toolbar select, .toolbar .btn { flex:1 1 100%; }
    }
@endsection

@section('sidebar')
    @include('partials.lider-sidebar')
@endsection

@section('content')
    <h1 class="page-title">Administración de Usuarios</h1>
    <p class="page-sub">Catálogo centralizado de cuentas institucionales, control de perfiles y asignación de roles de acceso</p>

    @if (session('status'))
        <div class="alert alert-ok" role="status" data-autocierre>{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif

    {{-- Búsqueda, filtros y alta --}}
    <form method="GET" action="{{ route('admin.usuarios.index') }}" class="panel toolbar" role="search">
        <input type="search" name="q" value="{{ $filtros['q'] }}" class="field-control search"
            placeholder="🔍 Buscar por nombre o matrícula..." aria-label="Buscar por nombre o matrícula">
        <select name="rol" class="field-control" aria-label="Filtrar por rol" onchange="this.form.submit()">
            <option value="">Todos los roles</option>
            @foreach ($roles as $clave => $nombre)
                <option value="{{ $clave }}" @selected($filtros['rol'] === $clave)>{{ $nombre }}</option>
            @endforeach
        </select>
        <select name="estado" class="field-control" aria-label="Filtrar por estado" onchange="this.form.submit()">
            <option value="activo" @selected($filtros['estado'] === 'activo')>Activo</option>
            <option value="inactivo" @selected($filtros['estado'] === 'inactivo')>Inactivo</option>
            <option value="todos" @selected($filtros['estado'] === 'todos')>Todos</option>
        </select>
        <button type="button" class="btn btn-navy" id="btnNuevo">＋ Nuevo Usuario</button>
    </form>

    {{-- Catálogo --}}
    <section class="panel table-card" aria-label="Catálogo de usuarios">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Matrícula / ID</th>
                        <th scope="col">Nombre Completo</th>
                        <th scope="col">Correo Institucional</th>
                        <th scope="col" class="center">Rol Asignado</th>
                        <th scope="col" class="center">Estado</th>
                        <th scope="col" class="center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($usuarios as $u)
                        @php
                            $esSelf = $u->is($user);
                            $esLider = $u->role === 'lider';
                        @endphp
                        <tr>
                            <td>{{ $u->matricula }}</td>
                            <td class="ev-name">{{ $u->nombre_completo }}</td>
                            <td>{{ $u->correo_institucional }}</td>
                            <td class="center"><span class="role-pill role-{{ $u->role }}">{{ $roles[$u->role] }}</span></td>
                            <td class="center">
                                @if ($esSelf)
                                    <span class="badge {{ $u->isActivo() ? 'b-ok' : 'b-bad' }}" title="No puedes desactivar tu propia cuenta">{{ $u->isActivo() ? 'Activo' : 'Inactivo' }}</span>
                                @else
                                    <form method="POST" action="{{ route('admin.usuarios.estatus', $u) }}" id="estatus-{{ $u->getKey() }}" style="display:inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                                        <input type="hidden" name="filtro_rol" value="{{ $filtros['rol'] }}">
                                        <input type="hidden" name="estado" value="{{ $filtros['estado'] }}">
                                        <input type="hidden" name="page" value="{{ $usuarios->currentPage() }}">
                                        <button type="button" class="badge estado-btn {{ $u->isActivo() ? 'b-ok' : 'b-bad' }}"
                                            title="{{ $u->isActivo() ? 'Clic para desactivar' : 'Clic para activar' }}"
                                            aria-label="Estado de {{ $u->nombre_completo }}: {{ $u->isActivo() ? 'activo' : 'inactivo' }}. Presiona para cambiarlo"
                                            data-confirm
                                            data-title="{{ $u->isActivo() ? 'Desactivar usuario' : 'Activar usuario' }}"
                                            data-message="¿Estás seguro de cambiar el estado de {{ $u->nombre_completo }} ({{ $u->matricula }}) a {{ $u->isActivo() ? 'Inactivo? No podrá iniciar sesión mientras esté inactivo.' : 'Activo? Podrá iniciar sesión nuevamente.' }}"
                                            data-ok-label="{{ $u->isActivo() ? 'Desactivar' : 'Activar' }}"
                                            data-submit="#estatus-{{ $u->getKey() }}">{{ $u->isActivo() ? 'Activo' : 'Inactivo' }} ⇄</button>
                                    </form>
                                @endif
                            </td>
                            <td class="center" style="white-space:nowrap">
                                <button type="button" class="act-btn act-edit btn-editar" aria-label="Editar a {{ $u->nombre_completo }}"
                                    data-action="{{ route('admin.usuarios.update', $u) }}"
                                    data-matricula="{{ $u->matricula }}"
                                    data-nombre="{{ $u->nombre }}"
                                    data-apellidos="{{ $u->apellidos }}"
                                    data-correo="{{ $u->correo_institucional }}"
                                    data-rol="{{ $u->role }}"
                                    data-estatus="{{ $u->estatus }}"
                                    data-id="{{ $u->getKey() }}"
                                    data-self="{{ $esSelf ? 1 : 0 }}"
                                    data-lider="{{ $esLider ? 1 : 0 }}">Editar</button>

                                @if ($esSelf || $esLider)
                                    <button type="button" class="act-btn act-del" disabled title="Las cuentas de Líder de Proyecto no se eliminan" aria-label="Eliminar no disponible">Eliminar</button>
                                @else
                                    <form method="POST" action="{{ route('admin.usuarios.destroy', $u) }}" id="del-{{ $u->getKey() }}" style="display:inline">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                                        <input type="hidden" name="filtro_rol" value="{{ $filtros['rol'] }}">
                                        <input type="hidden" name="estado" value="{{ $filtros['estado'] }}">
                                        <input type="hidden" name="page" value="{{ $usuarios->currentPage() }}">
                                        <button type="button" class="act-btn act-del" aria-label="Eliminar a {{ $u->nombre_completo }}"
                                            data-confirm
                                            data-title="Eliminar usuario"
                                            data-message="¿Deseas eliminar la cuenta de {{ $u->nombre_completo }} ({{ $u->matricula }})? Esta acción no se puede deshacer."
                                            data-ok-label="Eliminar"
                                            data-submit="#del-{{ $u->getKey() }}">Eliminar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">No se encontraron usuarios con los filtros aplicados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">
            <span>
                @if ($usuarios->total() > 0)
                    Mostrando {{ $usuarios->firstItem() }} a {{ $usuarios->lastItem() }} de {{ $usuarios->total() }} usuarios registrados
                @else
                    Sin usuarios registrados
                @endif
            </span>
            {{ $usuarios->links('partials.pagination') }}
        </div>
    </section>

    {{-- Formulario emergente (alta / edición) --}}
    <div class="modal-overlay" id="userModal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
        <form class="modal modal-form" method="POST" action="{{ route('admin.usuarios.store') }}" id="userForm" novalidate>
            @csrf
            <input type="hidden" name="_method" value="PUT" id="fMethod" disabled>
            <input type="hidden" name="_modo" value="{{ old('_modo', 'crear') }}" id="fModo">
            <input type="hidden" name="_id" value="{{ old('_id') }}" id="fId">
            <input type="hidden" name="q" value="{{ $filtros['q'] }}">
            <input type="hidden" name="filtro_rol" value="{{ $filtros['rol'] }}" id="fFiltroRol" disabled>
            <input type="hidden" name="estado" value="{{ $filtros['estado'] }}" disabled id="fEstadoFiltro">
            <input type="hidden" name="page" value="{{ $usuarios->currentPage() }}" disabled id="fPage">

            <div class="mf-head">
                <h3 id="userModalTitle">Registrar Nuevo Usuario</h3>
                <button type="button" class="mf-close" id="userModalClose" aria-label="Cerrar">✕</button>
            </div>

            <div class="mf-body">
                <p class="mf-intro" id="userIntro">Complete los datos para dar de alta una cuenta institucional con perfil asignado</p>

                <div class="mf-grid">
                    @php
                        $err = fn ($campo) => $errors->first($campo);
                    @endphp

                    <div class="mf-field {{ $err('nombre') ? 'invalid' : '' }}">
                        <label for="fNombre">Nombre(s) *</label>
                        <input type="text" id="fNombre" name="nombre" class="field-control" maxlength="60" required
                            placeholder="Ej. Roberto Carlos" value="{{ old('nombre') }}">
                        @if ($err('nombre'))<div class="mf-error">{{ $err('nombre') }}</div>@endif
                    </div>

                    <div class="mf-field {{ $err('apellidos') ? 'invalid' : '' }}">
                        <label for="fApellidos">Apellidos *</label>
                        <input type="text" id="fApellidos" name="apellidos" class="field-control" maxlength="80" required
                            placeholder="Ej. Morales Gómez" value="{{ old('apellidos') }}">
                        @if ($err('apellidos'))<div class="mf-error">{{ $err('apellidos') }}</div>@endif
                    </div>

                    <div class="mf-field mf-full {{ $err('matricula') ? 'invalid' : '' }}">
                        <label for="fMatricula">Matrícula escolar / ID *</label>
                        <div class="input-ico">
                            <span class="ico" aria-hidden="true">🪪</span>
                            <input type="text" id="fMatricula" name="matricula" class="field-control" maxlength="10" required
                                placeholder="Ej. ITIO230001" value="{{ old('matricula') }}" style="text-transform:uppercase">
                        </div>
                        @if ($err('matricula'))<div class="mf-error">{{ $err('matricula') }}</div>@endif
                    </div>

                    <div class="mf-field mf-full {{ $err('correo_institucional') ? 'invalid' : '' }}">
                        <label for="fCorreo">Correo institucional (@upemor.edu.mx) *</label>
                        <div class="input-ico">
                            <span class="ico" aria-hidden="true">✉️</span>
                            <input type="email" id="fCorreo" name="correo_institucional" class="field-control" maxlength="120" required
                                placeholder="usuario@upemor.edu.mx" value="{{ old('correo_institucional') }}">
                        </div>
                        @if ($err('correo_institucional'))<div class="mf-error">{{ $err('correo_institucional') }}</div>@endif
                    </div>

                    <div class="mf-field mf-full {{ $err('rol') ? 'invalid' : '' }}">
                        <label for="fRol">Rol asignado en la plataforma *</label>
                        <select id="fRol" name="rol" class="field-control" required>
                            @foreach ($roles as $clave => $nombre)
                                <option value="{{ $clave }}" @selected(old('rol', 'estudiante') === $clave)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                        @if ($err('rol'))<div class="mf-error">{{ $err('rol') }}</div>@endif
                    </div>

                    <div class="mf-field mf-full" id="grupoEstatus" hidden>
                        <label for="fEstatus">Estado de la cuenta</label>
                        <select id="fEstatus" name="estatus" class="field-control">
                            <option value="activo" @selected(old('estatus', 'activo') === 'activo')>Activo</option>
                            <option value="inactivo" @selected(old('estatus') === 'inactivo')>Inactivo</option>
                        </select>
                        @if ($err('estatus'))<div class="mf-error">{{ $err('estatus') }}</div>@endif
                    </div>

                    <div class="mf-field {{ $err('password') ? 'invalid' : '' }}">
                        <label for="fPassword"><span id="lblPassword">Contraseña inicial *</span></label>
                        <div class="input-ico has-toggle">
                            <span class="ico" aria-hidden="true">🔒</span>
                            <input type="password" id="fPassword" name="password" class="field-control" minlength="8" required
                                autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                            @include('partials.toggle-password', ['target' => 'fPassword'])
                        </div>
                        @if ($err('password'))<div class="mf-error">{{ $err('password') }}</div>@endif
                    </div>

                    <div class="mf-field">
                        <label for="fPasswordConf">Confirmar contraseña *</label>
                        <div class="input-ico has-toggle">
                            <span class="ico" aria-hidden="true">🔒</span>
                            <input type="password" id="fPasswordConf" name="password_confirmation" class="field-control" minlength="8" required
                                autocomplete="new-password" placeholder="Repite la contraseña">
                            @include('partials.toggle-password', ['target' => 'fPasswordConf'])
                        </div>
                    </div>
                </div>
            </div>

            <div class="mf-foot">
                <button type="button" class="mf-cancel" id="userModalCancel">Cancelar</button>
                <button type="submit" class="mf-save" id="userSave">💾 Guardar Usuario</button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const overlay = document.getElementById('userModal');
            const form = document.getElementById('userForm');
            const $ = (id) => document.getElementById(id);
            const storeUrl = @json(route('admin.usuarios.store'));
            const reabrir = @json($reabrir);
            let lastFocus = null;

            function configurar(modo, ctx) {
                const editar = modo === 'editar';
                form.action = editar ? ctx.action : storeUrl;
                $('fMethod').disabled = !editar;
                $('fModo').value = modo;
                $('fId').value = editar ? (ctx.id || '') : '';
                $('fEstadoFiltro').disabled = !editar;
                $('fFiltroRol').disabled = !editar;
                $('fPage').disabled = !editar;
                $('userModalTitle').textContent = editar ? 'Editar Usuario' : 'Registrar Nuevo Usuario';
                $('userIntro').textContent = editar
                    ? 'Modifique los datos de la cuenta. Deje la contraseña en blanco para conservar la actual.'
                    : 'Complete los datos para dar de alta una cuenta institucional con perfil asignado';
                $('userSave').textContent = editar ? '💾 Guardar Cambios' : '💾 Guardar Usuario';
                $('lblPassword').textContent = editar ? 'Nueva contraseña (opcional)' : 'Contraseña inicial *';
                $('fPassword').required = !editar;
                $('fPasswordConf').required = !editar;
                $('grupoEstatus').hidden = !editar;

                // La cuenta propia no puede cambiar de rol ni desactivarse.
                $('fRol').disabled = editar && ctx.esSelf;
                $('fEstatus').disabled = editar && ctx.esSelf;

                // El formato estricto aplica a matrículas nuevas; las anteriores pueden tener otra longitud.
                $('fMatricula').maxLength = editar ? 20 : 10;
            }

            function abrir(trigger) {
                lastFocus = trigger || document.activeElement;
                overlay.classList.add('open');
                $('fNombre').focus();
            }

            function cerrar() {
                overlay.classList.remove('open');
                if (lastFocus) lastFocus.focus();
            }

            $('btnNuevo').addEventListener('click', (e) => {
                form.reset();
                form.querySelectorAll('.invalid').forEach((el) => el.classList.remove('invalid'));
                form.querySelectorAll('.mf-error').forEach((el) => el.remove());
                configurar('crear', {});
                abrir(e.currentTarget);
            });

            document.querySelectorAll('.btn-editar').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const d = btn.dataset;
                    form.reset();
                    form.querySelectorAll('.invalid').forEach((el) => el.classList.remove('invalid'));
                    form.querySelectorAll('.mf-error').forEach((el) => el.remove());
                    configurar('editar', { action: d.action, id: d.id, esSelf: d.self === '1', esLider: d.lider === '1' });
                    $('fNombre').value = d.nombre;
                    $('fApellidos').value = d.apellidos;
                    $('fMatricula').value = d.matricula;
                    $('fCorreo').value = d.correo;
                    $('fRol').value = d.rol;
                    $('fEstatus').value = d.estatus;
                    abrir(btn);
                });
            });

            $('userModalClose').addEventListener('click', cerrar);
            $('userModalCancel').addEventListener('click', cerrar);
            // Clic fuera del cuadro o Escape piden confirmación si hay datos sin guardar.
            const intentarCerrar = protegerModal(overlay, form, cerrar);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) intentarCerrar(); });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && overlay.classList.contains('open')) intentarCerrar();
            });

            // Evita doble envío y valida campos obligatorios en el navegador antes de enviar.
            form.addEventListener('submit', (e) => {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    form.reportValidity();
                    return;
                }
                $('userSave').disabled = true;
            });

            // Tras un error de validación del servidor, se reabre el formulario con los datos capturados.
            if (reabrir) {
                configurar(reabrir.modo, reabrir);
                abrir(null);
            }
        })();
    </script>
@endsection
