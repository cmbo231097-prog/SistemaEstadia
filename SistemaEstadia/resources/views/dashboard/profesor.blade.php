@extends('layouts.panel')

@section('title', 'Panel de Control')

@section('styles')
    @include('partials.dashboard-styles')
@endsection

@section('sidebar')
    @include('partials.profesor-sidebar')
@endsection

@section('content')
    <h1 class="page-title">Panel de Control del Docente</h1>
    <p class="page-sub">Recepción de lotes PDF descargados de Classroom, evaluación por rúbrica y análisis frente a IA</p>

    {{-- KPIs --}}
    <section class="kpis" aria-label="Indicadores clave">
        <article class="panel kpi">
            <div class="kpi-label">Mis Grupos Asignados</div>
            <div class="kpi-value c-navy">{{ $kpis['grupos'] }} {{ $kpis['grupos'] === 1 ? 'Grupo' : 'Grupos' }}</div>
            <div class="kpi-note c-blue">👥 {{ $kpis['alumnos'] }} Alumnos ({{ $kpis['equipos'] }} equipos de 3)</div>
        </article>
        <article class="panel kpi">
            <div class="kpi-label">PDFs Sanitizados / Subidos</div>
            <div class="kpi-value c-green">{{ $kpis['pdfs_sanitizados'] }} / {{ $kpis['pdfs_subidos'] }}</div>
            <div class="kpi-note c-green">🛡️ {{ number_format($kpis['pdfs_sanitizados'] / max($kpis['pdfs_subidos'], 1) * 100, 1) }}% listos para evaluar</div>
        </article>
        <article class="panel kpi {{ $kpis['pdfs_pendientes'] > 0 ? 'alert' : '' }}">
            <div class="kpi-label">PDFs por Calificar</div>
            <div class="kpi-value c-warn">{{ $kpis['pdfs_pendientes'] }} PDFs</div>
            <div class="kpi-note c-warn">
                {{ $kpis['pdfs_pendientes'] > 0 ? '⏳ Pendientes de captura' : '✅ Sin pendientes' }}
            </div>
        </article>
        <article class="panel kpi">
            <div class="kpi-label">Tiempo Medio de Revisión</div>
            <div class="kpi-value c-purple">{{ number_format($kpis['tiempo_medio'], 1) }} min</div>
            <div class="kpi-note c-purple">⏱️ Por PDF (equipo de 3)</div>
        </article>
    </section>

    {{-- Acciones prioritarias --}}
    <section class="panel actions" aria-label="Acciones del docente">
        <span class="actions-label">Acciones del Docente:</span>
        <button type="button" class="btn btn-navy" data-soon="Subir PDFs (Corpus)">📤 Subir PDFs (Corpus)</button>
        <button type="button" class="btn btn-indigo" data-soon="Calificar Pendientes">✍️ Calificar Pendientes</button>
        <a href="{{ route('rendimiento-grupal.index') }}" class="btn btn-green" style="text-decoration:none">📥 Rep. Rendimiento (.xlsx)</a>
    </section>

    {{-- Tabla de seguimiento --}}
    <h2 class="section-title">Listado de Actividades y Estado de Calificación por Equipos</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col">Evidencia / Asignatura</th>
                    <th scope="col">Equipo Asignado</th>
                    <th scope="col" class="center">Estado Humano</th>
                    <th scope="col" class="center">Estado IA</th>
                    <th scope="col" class="center">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($actividades as $act)
                    @php
                        $completo = $act['calificados'] >= $act['pdfs'] && $act['pdfs'] > 0;
                        $pendientes = $act['pdfs'] - $act['calificados'];
                    @endphp
                    <tr>
                        <td class="ev-name">
                            {{ $act['evidencia'] }}
                            <span class="ev-sub">{{ $act['asignatura'] }}</span>
                        </td>
                        <td>
                            <strong>{{ $act['grupo'] }}</strong>
                            <span class="ev-sub">{{ $act['pdfs'] }} PDFs · equipos de 3</span>
                        </td>
                        <td class="center">
                            @if ($completo)
                                <span class="badge b-ok">✓ Calificado ({{ $act['calificados'] }}/{{ $act['pdfs'] }})</span>
                            @elseif ($act['calificados'] > 0)
                                <span class="badge b-warn">⏳ En proceso ({{ $act['calificados'] }}/{{ $act['pdfs'] }})</span>
                            @else
                                <span class="badge b-bad">● Pendiente (0/{{ $act['pdfs'] }})</span>
                            @endif
                        </td>
                        <td class="center">
                            @switch($act['estado_ia'])
                                @case('listo')
                                    <span class="badge b-info">✓ Listo</span>
                                    @break
                                @case('procesando')
                                    <span class="badge b-warn">⏳ Procesando</span>
                                    @break
                                @default
                                    <span class="badge b-idle">⏸ En espera</span>
                            @endswitch
                        </td>
                        <td class="center">
                            @if ($completo)
                                <button type="button" class="row-btn navy" data-soon="Motor Comparativo">Ver Comparador</button>
                            @else
                                <button type="button" class="row-btn indigo" data-soon="Captura de Evaluación">✍️ Calificar</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Aún no hay PDFs recibidos para calificar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Barra de estado --}}
    <footer class="panel infra center" aria-label="Estado del servicio">
        <div class="infra-item">
            @if ($infra['api_en_linea'])
                <span class="dot" aria-hidden="true"></span><span class="infra-ok">Conexión API LLM: En línea</span>
            @else
                <span class="dot off" aria-hidden="true"></span><span class="infra-warn">Conexión API LLM: No disponible</span>
            @endif
        </div>
    </footer>
@endsection
