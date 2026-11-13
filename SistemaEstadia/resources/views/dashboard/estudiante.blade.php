@extends('layouts.panel')

@section('title', 'Panel de Calificaciones')

@section('styles')
    @include('partials.dashboard-styles')

    .filter-bar { justify-content:space-between; }
    .filter-group { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .filter-group label { font-size:13px; font-weight:700; }
    .filter-group select {
        min-width:230px; padding:11px 42px 11px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text);
    }
    .btn-export { flex:0 1 340px; max-width:none; }
    .pts-pill { display:inline-block; padding:6px 16px; border-radius:999px; font-size:11.5px; font-weight:700; white-space:nowrap; }
    .grade-doc { color:#2f8f5b; font-weight:700; }
    .grade-ia { color:var(--blue); font-weight:700; }
    .infra-note { color:var(--muted); }
    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }
    .role-request { justify-content:space-between; }
    .role-request p { margin:2px 0 0; font-size:12px; color:var(--muted); max-width:70ch; }
    .role-request .btn { flex:0 1 300px; max-width:none; }
    .role-request .btn:disabled { opacity:.6; cursor:not-allowed; }
    @media (max-width: 560px) {
        .filter-group { width:100%; }
        .filter-group select { width:100%; }
        .btn-export { flex:1 1 100%; }
    }
@endsection

@section('sidebar')
    @include('partials.estudiante-sidebar')
@endsection

@section('content')
    <h1 class="page-title">Panel de Calificaciones y Retroalimentación</h1>
    <p class="page-sub">Consulta el comparativo de calificaciones por rúbrica entre el profesor y el modelo de IA</p>

    @if (session('status'))
        <div class="alert alert-ok" role="status" data-autocierre>{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif

    {{-- Solicitud para ser profesor --}}
    <section class="panel actions role-request" aria-label="Solicitud de rol de profesor">
        <div>
            <strong>¿Eres docente?</strong>
            <p>
                @if ($solicitudProfesor)
                    Tu solicitud para ser profesor se envió el {{ $solicitudProfesor->created_at?->format('d/m/Y H:i') }} y está pendiente de revisión por el Líder del proyecto.
                @else
                    Solicita el cambio a rol de Profesor. El Líder del proyecto recibirá un correo con tu solicitud y, si la acepta, cambiará tu rol.
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('solicitud-profesor.store') }}" id="solicitudProfesorForm">
            @csrf
            <button type="button" class="btn btn-navy" @disabled($solicitudProfesor !== null)
                data-confirm data-submit="#solicitudProfesorForm"
                data-title="Solicitar el rol de Profesor"
                data-message="Se enviará un correo al Líder del proyecto con tu nombre, matrícula y correo institucional. Mientras la solicitud esté pendiente no podrás enviar otra. ¿Deseas continuar?"
                data-ok-label="Enviar solicitud">
                🧑‍🏫 {{ $solicitudProfesor ? 'Solicitud pendiente' : 'Solicitar cambio a Profesor' }}
            </button>
        </form>
    </section>

    {{-- KPIs --}}
    <section class="kpis" aria-label="Indicadores personales">
        <article class="panel kpi">
            <div class="kpi-label">Evidencias Evaluadas</div>
            <div class="kpi-value c-navy">{{ $kpis['evaluadas'] }} {{ $kpis['evaluadas'] === 1 ? 'Actividad' : 'Actividades' }}</div>
            <div class="kpi-note c-green">✓ Todas concluidas</div>
        </article>
        <article class="panel kpi">
            <div class="kpi-label">Promedio del Docente</div>
            <div class="kpi-value c-blue">{{ number_format($kpis['promedio_docente'], 1) }} / 10</div>
            <div class="kpi-note c-blue">✍️ Calificación oficial</div>
        </article>
        <article class="panel kpi">
            <div class="kpi-label">Promedio de IA (Gemini)</div>
            <div class="kpi-value c-green">{{ number_format($kpis['promedio_ia'], 1) }} / 10</div>
            <div class="kpi-note c-green">🤖 Retroalimentación analítica</div>
        </article>
        <article class="panel kpi">
            <div class="kpi-label">Discrepancia Promedio</div>
            <div class="kpi-value c-purple">{{ number_format($kpis['discrepancia'], 1) }} %</div>
            <div class="kpi-note c-purple">⚖️ Coherencia evaluativa {{ $kpis['coherencia'] }}</div>
        </article>
    </section>

    {{-- Filtro y exportación --}}
    <section class="panel actions filter-bar" aria-label="Filtros y exportación">
        <div class="filter-group">
            <label for="filtroAsignatura">Filtrar por asignatura:</label>
            <select id="filtroAsignatura">
                <option value="">Todas las asignaturas</option>
                @foreach ($asignaturas as $asignatura)
                    <option value="{{ $asignatura }}">{{ $asignatura }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('contraste.index') }}" class="btn btn-navy btn-export" style="text-decoration:none;text-align:center">📄 Descargar Mi Reporte de Contraste (PDF)</a>
    </section>

    {{-- Historial --}}
    <h2 class="section-title">Historial de Evaluaciones y Comparativa de Calificaciones</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col">Evidencia / Asignatura</th>
                    <th scope="col" class="center">Equipo (3 Integrantes)</th>
                    <th scope="col" class="center">Calif. Docente</th>
                    <th scope="col" class="center">Calif. IA</th>
                    <th scope="col" class="center">Diferencia</th>
                    <th scope="col" class="center">Acción</th>
                </tr>
            </thead>
            <tbody id="historial">
                @foreach ($evaluaciones as $ev)
                    @php
                        $dif = round($ev['calif_ia'] - $ev['calif_docente'], 1);
                        $abs = abs($dif);
                        $pill = $abs <= 0.5 ? 'b-ok' : ($abs <= 1 ? 'b-warn' : 'b-bad');
                    @endphp
                    <tr data-asignatura="{{ $ev['asignatura'] }}">
                        <td class="ev-name">
                            {{ $ev['evidencia'] }}
                            <span class="ev-sub">{{ $ev['asignatura'] }}</span>
                        </td>
                        <td class="center">{{ $ev['equipo'] }}</td>
                        <td class="center grade-doc">{{ number_format($ev['calif_docente'], 1) }} / 10</td>
                        <td class="center grade-ia">{{ number_format($ev['calif_ia'], 1) }} / 10</td>
                        <td class="center">
                            <span class="pts-pill {{ $pill }}">{{ $dif > 0 ? '+' : '' }}{{ number_format($dif, 1) }} pts</span>
                        </td>
                        <td class="center">
                            <button type="button" class="row-btn navy" data-soon="Contraste de rúbrica">Ver Contraste</button>
                        </td>
                    </tr>
                @endforeach
                <tr id="sinResultados" hidden>
                    <td colspan="6" class="empty">No hay evaluaciones para la asignatura seleccionada.</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Barra de estado --}}
    <footer class="panel infra" aria-label="Estado del módulo">
        <div class="infra-item">
            <span class="dot" aria-hidden="true"></span><span class="infra-ok">Módulo de Consulta: En línea</span>
        </div>
        <div class="infra-item infra-note">ℹ️ Las evaluaciones son de carácter formativo para el proyecto de investigación</div>
    </footer>
@endsection

@section('scripts')
    <script>
        (function () {
            const select = document.getElementById('filtroAsignatura');
            const rows = document.querySelectorAll('#historial tr[data-asignatura]');
            const empty = document.getElementById('sinResultados');

            select.addEventListener('change', () => {
                let visibles = 0;
                rows.forEach((row) => {
                    const show = !select.value || row.dataset.asignatura === select.value;
                    row.hidden = !show;
                    if (show) visibles++;
                });
                empty.hidden = visibles > 0;
            });
        })();
    </script>
@endsection
