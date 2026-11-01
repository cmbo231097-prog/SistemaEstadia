@extends('layouts.panel')

@section('title', 'Dashboard General')

@section('styles')
    @include('partials.dashboard-styles')
@endsection

@section('sidebar')
    @include('partials.lider-sidebar')
@endsection

@section('content')
    <h1 class="page-title">Panel de Control del Líder de Proyecto / Investigador</h1>
    <p class="page-sub">Monitoreo de ingesta de corpus, ejecución del modelo LLM y métricas comparativas</p>

    {{-- KPIs --}}
    <section class="kpis" aria-label="Indicadores clave">
        <article class="panel kpi">
            <div class="kpi-label">Corpus Sanitizados</div>
            <div class="kpi-value c-navy">{{ $kpis['sanitizados'] }} / {{ $kpis['total_corpus'] }}</div>
            <div class="kpi-note c-green">📄 {{ number_format($kpis['sanitizados'] / max($kpis['total_corpus'], 1) * 100, 1) }}% Anonimizados</div>
        </article>
        <article class="panel kpi">
            <div class="kpi-label">Evaluados por IA</div>
            <div class="kpi-value c-blue">{{ $kpis['evaluados_ia'] }}</div>
            <div class="kpi-note c-warn">🕒 {{ $kpis['en_cola'] }} en cola de API</div>
        </article>
        <article class="panel kpi">
            <div class="kpi-label">Discrepancia Promedio</div>
            <div class="kpi-value c-green">{{ number_format($kpis['discrepancia'], 1) }} %</div>
            <div class="kpi-note c-muted">⚖️ {{ $kpis['discrepancia_nota'] }}</div>
        </article>
        <article class="panel kpi">
            <div class="kpi-label">Eficiencia de Tiempo</div>
            <div class="kpi-value c-purple">{{ number_format($kpis['eficiencia'], 1) }} %</div>
            <div class="kpi-note c-purple">⏱️ Ahorro estimado vs docente</div>
        </article>
    </section>

    {{-- Acciones prioritarias --}}
    <section class="panel actions" aria-label="Acciones prioritarias">
        <span class="actions-label">Acciones Prioritarias:</span>
        <button type="button" class="btn btn-navy" data-soon="Cargar y Sanitizar">＋ Cargar y Sanitizar</button>
        <button type="button" class="btn btn-blue" data-confirm
            data-title="Enviar lote a IA"
            data-message="Se enviarán a la API del LLM los lotes sanitizados y pendientes de evaluación. ¿Deseas continuar?"
            data-ok-label="Enviar lote"
            data-success="Lote enviado a la cola de evaluación (simulado).">🚀 Enviar Lote a IA</button>
        <button type="button" class="btn btn-green" data-soon="Reporte de Discrepancia (PDF)">📄 Rep. Discrepancia (PDF)</button>
        <button type="button" class="btn btn-orange" data-soon="Reporte de Tiempos (XLSX)">📊 Rep. Tiempos (Excel)</button>
    </section>

    {{-- Tabla de lotes --}}
    <h2 class="section-title">Estado de Lotes de Evidencias y Procesamiento de Corpus</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col">Evidencia / Asignatura</th>
                    <th scope="col">Profesor Titular</th>
                    <th scope="col" class="center">Sanitización</th>
                    <th scope="col" class="center">Estado IA</th>
                    <th scope="col" class="center">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lotes as $lote)
                    @php
                        $sanitizado = $lote['sanitizados'] >= $lote['total'] && $lote['total'] > 0;
                    @endphp
                    <tr>
                        <td class="ev-name">{{ $lote['evidencia'] }}</td>
                        <td>{{ $lote['profesor'] }}</td>
                        <td class="center">
                            @if ($sanitizado)
                                <span class="badge b-ok">✓ 100% Sin portada</span>
                            @else
                                <span class="badge b-bad">⚠ Pendiente ({{ $lote['sanitizados'] }}/{{ $lote['total'] }})</span>
                            @endif
                        </td>
                        <td class="center">
                            @switch($lote['estado_ia'])
                                @case('evaluado')
                                    <span class="badge b-info">✓ Evaluado (JSON)</span>
                                    @break
                                @case('procesando')
                                    <span class="badge b-warn">⏳ Procesando API ({{ $lote['progreso'] }}%)</span>
                                    @break
                                @default
                                    <span class="badge b-idle">⏸ En espera</span>
                            @endswitch
                        </td>
                        <td class="center">
                            @if ($lote['estado_ia'] === 'evaluado')
                                <button type="button" class="row-btn navy" data-soon="Motor Comparativo">Ver Comparador</button>
                            @elseif ($lote['estado_ia'] === 'procesando')
                                <button type="button" class="row-btn ghost" data-soon="Progreso del lote">Ver Progreso</button>
                            @else
                                <button type="button" class="row-btn blue" data-confirm
                                    data-title="Sanitizar lote"
                                    data-message="Se eliminarán las portadas y datos identificables de los {{ $lote['total'] }} PDF del lote «{{ $lote['evidencia'] }}». ¿Deseas continuar?"
                                    data-ok-label="Sanitizar"
                                    data-success="Sanitización del lote iniciada (simulado).">Sanitizar Lote</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Aún no hay lotes registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Estado de infraestructura --}}
    <footer class="panel infra" aria-label="Estado de infraestructura">
        <div class="infra-item">
            @if ($infra['api_en_linea'])
                <span class="dot" aria-hidden="true"></span><span class="infra-ok">Conexión API LLM: En línea</span>
            @else
                <span class="dot off" aria-hidden="true"></span><span class="infra-warn">Conexión API LLM: No disponible</span>
            @endif
        </div>
        <div class="infra-item">💾 Último Respaldo BD: {{ $infra['ultimo_respaldo'] }}</div>
        <div class="infra-item">
            <button type="button" class="infra-notif" data-soon="Notificaciones">
                🔔 {{ $infra['notificaciones'] }} {{ $infra['notificaciones'] === 1 ? 'Notificación pendiente' : 'Notificaciones pendientes' }}
            </button>
        </div>
    </footer>
@endsection
