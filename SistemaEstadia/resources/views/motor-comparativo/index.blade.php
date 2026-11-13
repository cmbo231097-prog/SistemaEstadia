@extends('layouts.panel')

@section('title', 'Motor Comparativo')

@section('styles')
    @include('partials.dashboard-styles')

    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-ok { background:var(--ok-bg); color:var(--ok-fg); border-color:#a7e3bd; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }

    .selectors { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; padding:14px 16px; margin-bottom:16px; }
    .field-control {
        width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text);
    }
    .selectors select:last-child { border-color:var(--blue); color:var(--blue); font-weight:700; }

    .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; }
    .page-head form { margin:0; }
    .c-ok { color:var(--ok-fg); } .c-bad { color:var(--bad-fg); } .c-info { color:var(--info-fg); }

    .table-card { overflow:hidden; margin-bottom:14px; }
    .table-card .table-wrap { border:0; border-radius:0; margin:0; }
    table { min-width:980px; }
    .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .badge { display:inline-block; padding:5px 12px; border-radius:999px; font-size:11.5px; font-weight:700; white-space:nowrap; }
    .b-ok { background:var(--ok-bg); color:var(--ok-fg); }
    .b-bad { background:var(--bad-bg); color:var(--bad-fg); }
    .b-wait { background:var(--warn-bg); color:var(--warn-fg); }
    .pending { font-size:12px; color:var(--muted); }
    .detail summary { cursor:pointer; font-size:12px; font-weight:700; color:var(--blue); }
    .detail table { min-width:0; margin-top:8px; font-size:12px; }
    .detail td, .detail th { padding:6px 8px; }
    .detail .comments { color:var(--muted); white-space:pre-line; }
    .btn:disabled { opacity:.5; cursor:not-allowed; filter:none; }

    @media (max-width: 760px) { .selectors { grid-template-columns:1fr; } }
@endsection

@section('sidebar')
    @include($user->role === 'lider' ? 'partials.lider-sidebar' : 'partials.profesor-sidebar')
@endsection

@section('content')
    @php
        $fmt = fn ($n, $d = 2) => number_format((float) $n, $d, '.', '');
        $signo = fn ($n) => ((float) $n > 0 ? '+' : '').number_format((float) $n, 2, '.', '');
        $conAmbas = $filas->filter(fn ($f) => $f['ia'] && $f['docente'])->count();
    @endphp

    <div class="page-head">
        <div>
            <h1 class="page-title">Motor Comparativo</h1>
            <p class="page-sub">Cruza la calificación de Gemini con la evaluación manual del docente: margen de error porcentual, varianza y diferencia de tiempos de revisión</p>
        </div>
        @if ($evidencia && $grupo)
            <form method="POST" action="{{ route('motor-comparativo.procesar') }}">
                @csrf
                <input type="hidden" name="id_evidencia" value="{{ $evidencia->id_evidencia }}">
                <input type="hidden" name="id_grupo" value="{{ $grupo->id_grupo }}">
                <button type="submit" class="btn btn-navy" @disabled($conAmbas === 0)>⚖️ Procesar lote</button>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" data-autocierre>{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('motor-comparativo.index') }}" class="panel selectors">
        <select name="asignatura" class="field-control" aria-label="Asignatura" onchange="this.form.grupo.value='';this.form.evidencia.value='';this.form.submit()">
            @forelse ($asignaturas as $a)
                <option value="{{ $a->id_asignatura }}" @selected($asignatura?->id_asignatura === $a->id_asignatura)>{{ $a->nombre_asignatura }}</option>
            @empty
                <option value="">Sin asignaturas asignadas</option>
            @endforelse
        </select>
        <select name="grupo" class="field-control" aria-label="Grupo" onchange="this.form.evidencia.value='';this.form.submit()">
            @forelse ($grupos as $g)
                <option value="{{ $g->id_grupo }}" @selected($grupo?->id_grupo === $g->id_grupo)>{{ $g->clave_grupo }}</option>
            @empty
                <option value="">Sin grupos</option>
            @endforelse
        </select>
        <select name="evidencia" class="field-control" aria-label="Evidencia" onchange="this.form.submit()">
            @forelse ($evidencias as $e)
                <option value="{{ $e->id_evidencia }}" @selected($evidencia?->id_evidencia === $e->id_evidencia)>{{ $e->titulo }}</option>
            @empty
                <option value="">Sin evidencias para este grupo</option>
            @endforelse
        </select>
    </form>

    @if (! $evidencia)
        <section class="panel"><p class="empty">Selecciona una evidencia de un grupo para comparar sus evaluaciones.</p></section>
    @else
        <div class="kpis" aria-label="Resumen del lote">
            <div class="panel kpi">
                <div class="kpi-label">Documentos comparados</div>
                <div class="kpi-value">{{ $resumen['documentos'] ?? 0 }} <span class="kpi-label">de {{ $filas->count() }}</span></div>
                <div class="kpi-note c-info">{{ $conAmbas }} con ambas evaluaciones</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Margen de error promedio</div>
                <div class="kpi-value">{{ $resumen ? $fmt($resumen['margen_error_promedio']).' %' : '—' }}</div>
                <div class="kpi-note c-info">{{ $resumen ? '≈ '.$fmt($resumen['discrepancia_promedio']).' pts de 100' : 'Sin datos' }}</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Varianza promedio</div>
                <div class="kpi-value">{{ $resumen ? $fmt($resumen['varianza_promedio'], 4) : '—' }}</div>
                <div class="kpi-note c-info">Escala de 0 a 10</div>
            </div>
            <div class="panel kpi {{ ($resumen['alertas'] ?? 0) > 0 ? 'alert' : '' }}">
                <div class="kpi-label">Diferencia de tiempos promedio</div>
                <div class="kpi-value">{{ $resumen ? $signo($resumen['diferencia_tiempos_promedio']).' min' : '—' }}</div>
                <div class="kpi-note {{ ($resumen['alertas'] ?? 0) > 0 ? 'c-bad' : 'c-ok' }}">
                    {{ $resumen ? $resumen['alertas'].' con alerta de discrepancia' : 'Sin datos' }}
                </div>
            </div>
        </div>

        <section class="panel table-card" aria-label="Comparación por documento">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Equipo</th>
                            <th scope="col" class="num">IA (/100)</th>
                            <th scope="col" class="num">Docente (/100)</th>
                            <th scope="col" class="num">Discrepancia (pts)</th>
                            <th scope="col" class="num">Margen de error</th>
                            <th scope="col" class="num">Varianza</th>
                            <th scope="col" class="num">Tiempo IA / docente</th>
                            <th scope="col" class="num">Diferencia</th>
                            <th scope="col" class="center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            @php
                                $c = $fila['comparativa'];
                                $doc = $fila['documento'];
                            @endphp
                            <tr>
                                <td><strong>{{ $doc->equipo?->nombre_equipo ?? 'Documento '.$doc->id_corpus }}</strong></td>
                                @if ($c)
                                    <td class="num">{{ $fmt($fila['puntajes']['ia']) }}</td>
                                    <td class="num">{{ $fmt($fila['puntajes']['docente']) }}</td>
                                    <td class="num">{{ $fmt($c->discrepancia_absoluta) }}</td>
                                    <td class="num">{{ $fmt($c->discrepancia_porcentual) }} %</td>
                                    <td class="num">{{ $fmt($c->varianza, 4) }}</td>
                                    <td class="num">{{ $fmt($fila['ia']->tiempo_procesamiento_segundos / 60) }} / {{ $fmt($fila['docente']->tiempo_evaluacion_minutos) }} min</td>
                                    <td class="num">{{ $signo($c->diferencia_tiempos_minutos) }} min</td>
                                    <td class="center">
                                        @if ($c->alerta_discrepancia)
                                            <span class="badge b-bad">⚠ Discrepancia alta</span>
                                        @else
                                            <span class="badge b-ok">✓ Coherente</span>
                                        @endif
                                    </td>
                                @else
                                    <td colspan="7" class="pending">{{ $fila['motivo'] ?? 'Sin comparar.' }}</td>
                                    <td class="center"><span class="badge b-wait">⏳ Pendiente</span></td>
                                @endif
                            </tr>
                            @if ($c && $c->detalle->isNotEmpty())
                                <tr>
                                    <td colspan="9">
                                        <details class="detail">
                                            <summary>Ver comparación por criterio</summary>
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th scope="col">Criterio</th>
                                                        <th scope="col" class="num">Docente</th>
                                                        <th scope="col" class="num">IA</th>
                                                        <th scope="col" class="num">Diferencia</th>
                                                        <th scope="col">Retroalimentación</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($c->detalle as $d)
                                                        <tr>
                                                            <td>{{ $rubrica[$d->id_criterio]->nombre_criterio ?? 'Criterio '.$d->id_criterio }}</td>
                                                            <td class="num">{{ $fmt($d->calificacion_docente) }} / {{ $fmt($rubrica[$d->id_criterio]->puntaje_maximo ?? 0) }}</td>
                                                            <td class="num">{{ $fmt($d->calificacion_ia) }}</td>
                                                            <td class="num">{{ $fmt($d->discrepancia_criterio) }}</td>
                                                            <td class="comments">{{ $d->comentarios_criterio }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </details>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="9" class="empty">Aún no hay documentos cargados para esta evidencia.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
