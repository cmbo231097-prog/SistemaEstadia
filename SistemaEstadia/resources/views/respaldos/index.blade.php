@extends('layouts.panel')

@section('title', 'Respaldo de Base de Datos')

@section('styles')
    @include('partials.dashboard-styles')

    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }

    .stats { display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; margin-bottom:16px; }
    .stat { padding:14px 16px; }
    .stat small { display:block; color:var(--muted); font-size:11.5px; margin-bottom:6px; }
    .stat strong { display:block; font-size:19px; color:var(--navy); }
    .stat span { display:block; margin-top:6px; font-size:11.5px; font-weight:700; }
    .stat .c-ok { color:var(--ok-fg); }
    .stat .c-blue { color:var(--blue); }

    .actions-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px; }
    .action-card { padding:18px 20px; }
    .action-card h2 { margin:0 0 6px; font-size:14px; }
    .action-card p { margin:0 0 16px; font-size:12px; color:var(--muted); }
    .action-row { display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
    .action-card .btn { flex:1 1 220px; max-width:none; }
    .check { font-size:12px; color:var(--muted); display:flex; gap:6px; align-items:center; }
    .btn-danger { background:#b42318; }
    .btn:disabled { opacity:.5; cursor:not-allowed; filter:none; }
    .filebox {
        flex:1 1 200px; padding:11px 12px; border:1px solid var(--border); border-radius:8px; background:#fff;
        font-family:inherit; font-size:12px; color:var(--muted); text-align:left; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    }
    .warn-title { color:#b45309; }

    .section-title { font-size:14px; margin:0 0 10px; }
    .table-card { overflow:hidden; margin-bottom:14px; }
    .table-card .table-wrap { border:0; border-radius:0; margin:0; }
    .table-foot { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; padding:12px 16px; border-top:1px solid var(--border); font-size:12px; color:var(--muted); }
    .table-actions { white-space:nowrap; }
    .table-actions form { display:inline; }
    .act-btn { background:none; border:1px solid transparent; font-size:12px; font-weight:700; padding:6px 6px; border-radius:6px; color:var(--blue); text-decoration:none; display:inline-block; }
    .act-btn:hover { background:var(--info-bg); }
    .act-del { color:var(--bad-fg); }
    .act-del:hover { background:var(--bad-bg); }
    .scope { display:inline-block; padding:5px 14px; border-radius:999px; background:var(--ok-bg); color:var(--ok-fg); font-size:11.5px; font-weight:700; white-space:nowrap; }
    .scope.missing { background:var(--bad-bg); color:var(--bad-fg); }
    .info-note { padding:12px 16px; border-radius:10px; background:var(--info-bg); color:var(--info-fg); font-size:12px; border:1px solid #bfd3f2; }
    [hidden] { display:none !important; }

    @media (max-width: 960px) { .stats { grid-template-columns:1fr 1fr; } .actions-grid { grid-template-columns:1fr; } }
    @media (max-width: 520px) { .stats { grid-template-columns:1fr; } }
@endsection

@section('sidebar')
    @include('partials.lider-sidebar')
@endsection

@section('content')
    <h1 class="page-title">Mantenimiento y Respaldo de Base de Datos</h1>
    <p class="page-sub">Generación de volcados SQL relacionales, administración de puntos de restauración y recuperación ante contingencias</p>

    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-err" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="stats" aria-label="Estado de la base de datos">
        <div class="panel stat">
            <small>Gestor Relacional</small>
            <strong>{{ $gestor['motor'] }} {{ $gestor['version'] }}</strong>
            <span class="c-ok">✓ Motor en línea y activo</span>
        </div>
        <div class="panel stat">
            <small>Tamaño de la Base de Datos</small>
            <strong>{{ number_format($tamanoBd, 1) }} MB</strong>
            <span class="c-blue">🗄️ {{ $totalTablas + 1 }} tablas normalizadas</span>
        </div>
        <div class="panel stat">
            <small>Último Respaldo Generado</small>
            <strong>
                @if ($ultimo)
                    {{ $ultimo->fecha_respaldo->isToday() ? 'Hoy' : $ultimo->fecha_respaldo->format('d/m/Y') }}, {{ $ultimo->fecha_respaldo->format('H:i') }} hrs
                @else
                    Sin respaldos
                @endif
            </strong>
            <span class="c-ok">{{ $ultimo ? '✓ Volcado SQL completo' : 'Genera tu primera copia' }}</span>
        </div>
        <div class="panel stat">
            <small>Copias de Seguridad Activas</small>
            <strong>{{ $totalCopias }} {{ $totalCopias === 1 ? 'Archivo' : 'Archivos' }}</strong>
            <span class="c-blue">💾 {{ number_format($almacenamientoMb, 1) }} MB en almacenamiento</span>
        </div>
    </section>

    <section class="actions-grid">
        <div class="panel action-card">
            <h2>💾 Generar Nueva Copia de Seguridad</h2>
            <p>Exporta un archivo SQL completo con la estructura de tablas y todos los registros académicos actuales del sistema (usuarios, rúbricas y calificaciones).</p>
            <form method="POST" action="{{ route('respaldos.store') }}" id="formGenerar" class="action-row">
                @csrf
                <button type="submit" class="btn btn-navy" id="btnGenerar">💾 Generar Respaldo Ahora (.sql)</button>
                <label class="check"><input type="checkbox" name="comprimir" value="1"> Comprimir copia (.gz)</label>
            </form>
        </div>

        <div class="panel action-card">
            <h2 class="warn-title">⚠️ Restaurar Base de Datos desde Archivo</h2>
            <p>Selecciona un archivo SQL previo. Advertencia: esta acción reemplazará la información actual por el estado exacto de la copia de seguridad.</p>
            <form method="POST" action="{{ route('respaldos.restaurar-archivo') }}" enctype="multipart/form-data" id="formRestaurarArchivo" class="action-row">
                @csrf
                <input type="file" name="archivo" id="archivoSql" accept=".sql,.gz" hidden>
                <button type="button" class="filebox" id="btnArchivo">📁 Seleccionar archivo .sql...</button>
                <button type="button" class="btn btn-danger" id="btnRestaurarArchivo" disabled
                    data-confirm
                    data-title="Restaurar base de datos"
                    data-message="Se reemplazará toda la información actual por el contenido del archivo seleccionado. Antes se guardará una copia de seguridad automática. ¿Deseas continuar?"
                    data-ok-label="Restaurar"
                    data-submit="#formRestaurarArchivo">Restaurar Base de Datos</button>
            </form>
        </div>
    </section>

    <h2 class="section-title">Historial de Copias de Seguridad Almacenadas en el Servidor</h2>
    <section class="panel table-card" aria-label="Historial de respaldos">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Nombre del Archivo de Respaldo</th>
                        <th scope="col" class="center">Fecha y Hora de Creación</th>
                        <th scope="col" class="center">Tamaño</th>
                        <th scope="col" class="center">Alcance / Contenido</th>
                        <th scope="col" class="center">Acciones Disponibles</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($historial as $r)
                        <tr>
                            <td><strong>{{ $r->nombre_archivo }}</strong></td>
                            <td class="center">{{ $r->fecha_respaldo?->format('d/m/Y - H:i') }} hrs</td>
                            <td class="center">{{ number_format($r->tamano_mb, 1) }} MB</td>
                            <td class="center">
                                @if ($r->disponible)
                                    <span class="scope">{{ $r->alcance_tablas }}</span>
                                @else
                                    <span class="scope missing">Archivo no disponible</span>
                                @endif
                            </td>
                            <td class="center table-actions">
                                @if ($r->disponible)
                                    <a class="act-btn" href="{{ route('respaldos.descargar', $r) }}">Descargar</a> /
                                    <form method="POST" action="{{ route('respaldos.restaurar', $r) }}" id="res-{{ $r->id_respaldo }}">
                                        @csrf
                                        <button type="button" class="act-btn"
                                            data-confirm
                                            data-title="Restaurar base de datos"
                                            data-message="Se reemplazará toda la información actual por el contenido de «{{ $r->nombre_archivo }}». Antes se guardará una copia de seguridad automática. ¿Deseas continuar?"
                                            data-ok-label="Restaurar"
                                            data-submit="#res-{{ $r->id_respaldo }}">Restaurar</button>
                                    </form> /
                                @endif
                                <form method="POST" action="{{ route('respaldos.destroy', $r) }}" id="del-{{ $r->id_respaldo }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="act-btn act-del"
                                        data-confirm
                                        data-title="Eliminar respaldo"
                                        data-message="¿Deseas eliminar el respaldo «{{ $r->nombre_archivo }}»? Esta acción no se puede deshacer."
                                        data-ok-label="Eliminar"
                                        data-submit="#del-{{ $r->id_respaldo }}">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Aún no hay respaldos generados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">
            <span>
                @if ($historial->total() > 0)
                    Mostrando {{ $historial->count() }} de {{ $historial->total() }} respaldos almacenados en disco
                @else
                    Sin respaldos almacenados
                @endif
            </span>
            {{ $historial->links('partials.pagination') }}
        </div>
    </section>

    <div class="info-note">ℹ️ Las copias de seguridad generan volcados estructurados compatibles con MariaDB/MySQL. Se recomienda descargar y conservar una copia periódica en un medio de almacenamiento externo.</div>
@endsection

@section('scripts')
<script>
(() => {
    const input = document.getElementById('archivoSql');
    const boton = document.getElementById('btnArchivo');
    const restaurar = document.getElementById('btnRestaurarArchivo');
    const MAX = {{ $maxKb }} * 1024;
    const etiqueta = '📁 Seleccionar archivo .sql...';

    boton.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        const f = input.files[0];
        const valido = f && /\.(sql|gz)$/i.test(f.name) && f.size <= MAX;
        restaurar.disabled = !valido;
        boton.textContent = valido ? '📁 ' + f.name : etiqueta;
        if (f && !valido) {
            input.value = '';
            alert('Formato no admitido o archivo demasiado grande. Solo se aceptan archivos .sql o .sql.gz.');
        }
    });
    document.getElementById('formGenerar').addEventListener('submit', () => {
        const b = document.getElementById('btnGenerar');
        b.disabled = true;
        b.textContent = 'Generando respaldo…';
    });
})();
</script>
@endsection
