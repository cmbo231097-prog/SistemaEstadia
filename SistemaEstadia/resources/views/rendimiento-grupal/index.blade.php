@extends('layouts.panel')

@section('title', 'Reporte de Rendimiento Grupal')

@section('styles')
    @include('partials.dashboard-styles')

    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }

    .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; }
    .selectors { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; padding:14px 16px; margin-bottom:16px; }
    .field-control {
        width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text);
    }
    .selectors select:last-child { border-color:var(--blue); color:var(--blue); font-weight:700; }
    .btn-link { display:inline-block; text-decoration:none; text-align:center; flex:0 0 auto; max-width:none; padding:11px 18px; }
    .btn[aria-disabled="true"] { opacity:.5; cursor:not-allowed; pointer-events:none; }
    .hint { font-size:12px; color:var(--muted); margin:-6px 0 16px; }
    .c-info { color:var(--info-fg); }
    .kpi-value { font-size:24px; }

    .chart-card { padding:16px 18px 12px; margin-bottom:18px; }
    .chart-card h2 { margin:0 0 4px; font-size:14px; }
    .legend { display:flex; flex-wrap:wrap; gap:8px 16px; font-size:12px; color:var(--muted); margin:6px 0 8px; }
    .legend span::before { content:''; display:inline-block; width:11px; height:11px; border-radius:3px; margin-right:6px; vertical-align:-1px; background:var(--c); }
    .chart-scroll { overflow-x:auto; }
    .chart-scroll svg { display:block; height:auto; }
    .chart-scroll text { font-family:inherit; }

    .table-card { overflow:hidden; margin-bottom:18px; }
    .table-card h2 { margin:0; padding:14px 18px 4px; font-size:14px; }
    .table-card .table-wrap { border:0; border-radius:0; margin:0; }
    .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .tag { display:inline-block; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; }
    .tag-ia { background:var(--ok-bg); color:var(--ok-fg); }
    .tag-doc { background:var(--warn-bg); color:var(--warn-fg); }
    .muted { color:var(--muted); }
    .detail summary { cursor:pointer; font-size:12px; font-weight:700; color:var(--blue); }
    .detail table { min-width:0; margin-top:8px; font-size:12px; }
    .detail td, .detail th { padding:6px 8px; }
    .detail .comments { color:var(--muted); white-space:pre-line; }

    @media (max-width:760px) { .selectors { grid-template-columns:1fr; } }
@endsection

@section('sidebar')
    @include($user->role === 'lider' ? 'partials.lider-sidebar' : 'partials.profesor-sidebar')
@endsection

@section('content')
    @php
        $fmt = fn ($n, $d = 2) => number_format((float) $n, $d, '.', ',');
        $sd = fn ($n, $d = 2) => $n === null ? '—' : number_format((float) $n, $d, '.', ',');
    @endphp

    <div class="page-head">
        <div>
            <h1 class="page-title">Reporte de Rendimiento Grupal</h1>
            <p class="page-sub">Consulta las calificaciones y el desempeño por rubro de tu grupo, cruzando la evaluación docente con la de la IA</p>
        </div>
        @if ($reporte && $reporte['equipos']->isNotEmpty())
            <a href="{{ route('rendimiento-grupal.excel', $consultaExcel) }}" class="btn btn-green btn-link">📥 Descargar Excel</a>
        @else
            <span class="btn btn-green btn-link" aria-disabled="true">📥 Descargar Excel</span>
        @endif
    </div>

    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('rendimiento-grupal.index') }}" class="panel selectors" aria-label="Filtros del reporte">
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
    <p class="hint">Los rubros pertenecen a cada evidencia, por eso el reporte se consulta por evidencia del grupo. La IA solo califica los rubros marcados como evaluables desde el producto.</p>

    @if (! $reporte)
        <section class="panel"><p class="empty">Selecciona un grupo con evidencias para consultar su rendimiento.</p></section>
    @elseif ($reporte['equipos']->isEmpty())
        <section class="panel"><p class="empty">Este grupo aún no tiene documentos cargados para la evidencia seleccionada.</p></section>
    @else
        @php
            $r = $reporte['resumen'];
            $criterios = $reporte['criterios'];
            $barras = $reporte['barras'];
            $maxIa = rtrim(rtrim(number_format($r['ia_maximo'], 2, '.', ''), '0'), '.');
        @endphp

        <div class="kpis" aria-label="Resumen del grupo">
            <div class="panel kpi">
                <div class="kpi-label">Equipos con documento</div>
                <div class="kpi-value">{{ $r['equipos'] }}</div>
                <div class="kpi-note c-info">{{ $r['con_docente'] }} con evaluación docente · {{ $r['con_ia'] }} con IA</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Calificación promedio del docente</div>
                <div class="kpi-value">{{ $sd($r['docente_prom']) }}</div>
                <div class="kpi-note c-info">sobre 100</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Calificación promedio de la IA</div>
                <div class="kpi-value">{{ $sd($r['ia_prom']) }}</div>
                <div class="kpi-note c-info">sobre {{ $maxIa }} (rubros que evalúa la IA)</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Docente en los rubros de la IA</div>
                <div class="kpi-value">{{ $sd($r['docente_en_rubros_ia']) }}</div>
                <div class="kpi-note c-info">sobre {{ $maxIa }} · {{ $r['comparados'] }} equipo(s) comparables</div>
            </div>
        </div>

        @if ($barras->isEmpty())
            <section class="panel"><p class="empty">Aún no hay evaluaciones del docente ni de la IA para graficar.</p></section>
        @else
            @php
                // Gráfica de barras apiladas (SVG): por equipo, una barra del docente y otra de la IA; un segmento por rubro.
                $equiposGrafica = $barras->pluck('equipo')->unique()->values();
                $n = $equiposGrafica->count();
                $ancho = max(960, $n * 130 + 70);
                $alto = 380;
                $izq = 46; $der = 14; $arriba = 30; $abajo = 62;
                $plano = $alto - $arriba - $abajo;
                $ranura = ($ancho - $izq - $der) / $n;
                $barra = min(38, $ranura * 0.34);
                $y = fn ($valor) => $arriba + $plano * (1 - max(0, min($valor, 100)) / 100);
                $recorta = fn (string $texto, int $max) => mb_strlen($texto) > $max ? rtrim(mb_substr($texto, 0, $max - 1)).'…' : $texto;
                $haySinDesglose = $barras->contains('sin_desglose', true);
            @endphp
            <section class="panel chart-card" aria-labelledby="graficaTitulo">
                <h2 id="graficaTitulo">Calificación por rubro: docente vs. IA por equipo (puntos)</h2>
                <div class="legend">
                    @foreach ($criterios as $i => $c)
                        <span style="--c:#{{ \App\Services\RendimientoGrupalService::color($i) }}">{{ $c['nombre'] }} ({{ rtrim(rtrim(number_format($c['maximo'], 2, '.', ''), '0'), '.') }}){{ $c['ia'] ? '' : ' · solo docente' }}</span>
                    @endforeach
                    @if ($haySinDesglose)
                        <span style="--c:#94a3b8">Sin desglose por rubro</span>
                    @endif
                </div>
                <div class="chart-scroll">
                    <svg viewBox="0 0 {{ $ancho }} {{ $alto }}" style="min-width:{{ min($ancho, 720) }}px; width:100%" role="img"
                        aria-label="Gráfica de barras apiladas con la calificación por rubro del docente y de la IA para cada equipo. Los mismos datos aparecen en las tablas siguientes.">
                        @foreach ([0, 20, 40, 60, 80, 100] as $marca)
                            <line x1="{{ $izq }}" x2="{{ $ancho - $der }}" y1="{{ $y($marca) }}" y2="{{ $y($marca) }}" stroke="{{ $marca === 0 ? '#64748b' : '#e2e8f0' }}" stroke-width="1"/>
                            <text x="{{ $izq - 8 }}" y="{{ $y($marca) + 4 }}" text-anchor="end" font-size="11" fill="#64748b">{{ $marca }}</text>
                        @endforeach

                        @foreach ($equiposGrafica as $i => $equipo)
                            @php
                                $centro = $izq + $ranura * ($i + 0.5);
                                $delEquipo = $barras->where('equipo', $equipo)->keyBy('fuente');
                            @endphp
                            @foreach (['Docente' => $centro - $barra - 2, 'IA' => $centro + 2] as $fuente => $x)
                                @continue(! $delEquipo->has($fuente))
                                @php
                                    $b = $delEquipo[$fuente];
                                    $acumulado = 0;
                                    $segmentos = $b['sin_desglose'] ? [[null, $b['total'], '94a3b8', 'Total sin desglose por rubro']] : [];
                                    if (! $b['sin_desglose']) {
                                        foreach ($criterios as $k => $c) {
                                            if (isset($b['segmentos'][$c['id']])) {
                                                $segmentos[] = [$c['id'], $b['segmentos'][$c['id']], \App\Services\RendimientoGrupalService::color($k), $c['nombre'].' (máx. '.rtrim(rtrim(number_format($c['maximo'], 2, '.', ''), '0'), '.').')'];
                                            }
                                        }
                                    }
                                @endphp
                                <g>
                                    @foreach ($segmentos as [$idCriterio, $valor, $color, $nombreSegmento])
                                        @php
                                            $altura = $plano * max(0, min($valor, 100)) / 100;
                                            $yTop = $y($acumulado + $valor);
                                            $acumulado += $valor;
                                        @endphp
                                        @if ($altura > 0)
                                            <rect x="{{ $x }}" y="{{ $yTop }}" width="{{ $barra }}" height="{{ $altura }}" fill="#{{ $color }}" stroke="#fff" stroke-width="1">
                                                <title>{{ $equipo }} · {{ $fuente }} — {{ $nombreSegmento }}: {{ $fmt($valor) }}</title>
                                            </rect>
                                            @if ($altura >= 14)
                                                <text x="{{ $x + $barra / 2 }}" y="{{ $yTop + $altura / 2 + 4 }}" text-anchor="middle" font-size="10.5" fill="#fff" font-weight="600" pointer-events="none">{{ $fmt($valor, $valor == floor($valor) ? 0 : 1) }}</text>
                                            @endif
                                        @endif
                                    @endforeach
                                    <text x="{{ $x + $barra / 2 }}" y="{{ $y($b['total']) - 5 }}" text-anchor="middle" font-size="11.5" font-weight="700" fill="#0f172a">{{ $fmt($b['total'], 1) }}</text>
                                    <text x="{{ $x + $barra / 2 }}" y="{{ $arriba + $plano + 14 }}" text-anchor="middle" font-size="10.5" fill="#64748b">{{ $fuente === 'Docente' ? 'Docente' : 'IA' }}</text>
                                </g>
                            @endforeach
                            <text x="{{ $centro }}" y="{{ $arriba + $plano + 34 }}" text-anchor="middle" font-size="12" font-weight="600" fill="#0f172a">{{ $recorta($equipo, 18) }}</text>
                        @endforeach
                    </svg>
                </div>
            </section>
        @endif

        <section class="panel table-card" aria-label="Desempeño por rubro">
            <h2>Desempeño por rubro (promedio del grupo)</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Rubro</th>
                            <th scope="col" class="num">Máximo</th>
                            <th scope="col">Lo evalúa</th>
                            <th scope="col" class="num">Docente</th>
                            <th scope="col" class="num">% docente</th>
                            <th scope="col" class="num">IA</th>
                            <th scope="col" class="num">% IA</th>
                            <th scope="col" class="num">Dif. (IA − docente)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reporte['rubros'] as $rubro)
                            <tr>
                                <td><strong>{{ $rubro['nombre'] }}</strong></td>
                                <td class="num">{{ $fmt($rubro['maximo']) }}</td>
                                <td>@if ($rubro['ia'])<span class="tag tag-ia">IA y docente</span>@else<span class="tag tag-doc">Solo docente</span>@endif</td>
                                <td class="num">{{ $sd($rubro['docente_prom']) }}</td>
                                <td class="num">{{ $rubro['docente_pct'] === null ? '—' : $fmt($rubro['docente_pct'], 1).' %' }}</td>
                                <td class="num">{{ $rubro['ia'] ? $sd($rubro['ia_prom']) : '—' }}</td>
                                <td class="num">{{ $rubro['ia'] && $rubro['ia_pct'] !== null ? $fmt($rubro['ia_pct'], 1).' %' : '—' }}</td>
                                <td class="num">{{ $rubro['diferencia'] === null ? '—' : ($rubro['diferencia'] > 0 ? '+' : '').$fmt($rubro['diferencia']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel table-card" aria-label="Calificación por equipo">
            <h2>Calificación por equipo</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Equipo</th>
                            <th scope="col" class="num">Docente (/100)</th>
                            <th scope="col" class="num">IA (/{{ $maxIa }})</th>
                            <th scope="col">Detalle por rubro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reporte['equipos'] as $equipo)
                            <tr>
                                <td><strong>{{ $equipo['equipo'] }}</strong></td>
                                <td class="num">{{ $equipo['docente'] ? $fmt($equipo['docente']['total']) : '—' }}</td>
                                <td class="num">{{ $equipo['ia'] ? $fmt($equipo['ia']['total']) : '—' }}</td>
                                <td>
                                    @if (! $equipo['docente'] && ! $equipo['ia'])
                                        <span class="muted">Sin evaluar</span>
                                    @else
                                        <details class="detail">
                                            <summary>Ver rubros</summary>
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th scope="col">Rubro</th>
                                                        <th scope="col" class="num">Docente</th>
                                                        <th scope="col" class="num">IA</th>
                                                        <th scope="col">Retroalimentación</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($criterios as $c)
                                                        @php
                                                            $d = $equipo['docente']['criterios'][$c['id']] ?? null;
                                                            $ia = $equipo['ia']['criterios'][$c['id']] ?? null;
                                                            $retro = array_filter([
                                                                filled($equipo['docente']['retro'][$c['id']] ?? null) ? 'Docente: '.$equipo['docente']['retro'][$c['id']] : null,
                                                                filled($equipo['ia']['retro'][$c['id']] ?? null) ? 'IA: '.$equipo['ia']['retro'][$c['id']] : null,
                                                            ]);
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $c['nombre'] }}</td>
                                                            <td class="num">{{ $d === null ? '—' : $fmt($d).' / '.$fmt($c['maximo']) }}</td>
                                                            <td class="num">{{ $ia === null ? '—' : $fmt($ia) }}</td>
                                                            <td class="comments">{{ implode("\n", $retro) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
