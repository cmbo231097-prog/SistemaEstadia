@extends('layouts.panel')

@section('title', 'Reporte de Eficiencia de Tiempos')

@section('styles')
    @include('partials.dashboard-styles')

    .msg { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .msg-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }
    .msg ul { margin:0; padding-left:18px; }

    .filters { display:flex; flex-wrap:wrap; gap:14px; align-items:flex-end; padding:16px 18px; margin-bottom:16px; }
    .filters .field { display:flex; flex-direction:column; gap:5px; flex:1 1 180px; min-width:0; }
    .filters label { font-size:12px; font-weight:700; }
    .field-control {
        width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text);
    }
    .filters .buttons { display:flex; flex-wrap:wrap; gap:10px; align-items:center; flex:0 0 auto; }
    .filters .buttons .btn { flex:0 0 auto; max-width:none; padding:11px 18px; }
    .btn-link { display:inline-block; text-decoration:none; text-align:center; }
    .btn-ghost { background:#fff; border:1px solid var(--border); color:#334155; border-radius:8px; padding:10px 16px; font-size:12.5px; font-weight:700; text-decoration:none; display:inline-block; }
    .btn-ghost:hover { background:var(--idle-bg); }
    .btn[aria-disabled="true"] { opacity:.5; cursor:not-allowed; pointer-events:none; }
    .hint { font-size:12px; color:var(--muted); margin:-6px 0 16px; }
    .c-ok { color:var(--ok-fg); } .c-info { color:var(--info-fg); }
    .kpis { grid-template-columns:repeat(5, 1fr); }
    .kpi-value { font-size:24px; }

    .chart-card { padding:16px 18px 12px; margin-bottom:18px; }
    .chart-card h2 { margin:0 0 4px; font-size:14px; }
    .legend { display:flex; flex-wrap:wrap; gap:16px; font-size:12px; color:var(--muted); margin:0 0 8px; }
    .legend span::before { content:''; display:inline-block; width:11px; height:11px; border-radius:3px; margin-right:6px; vertical-align:-1px; background:var(--c); }
    .chart-scroll { overflow-x:auto; }
    .chart-scroll svg { display:block; height:auto; }
    .chart-scroll text { font-family:inherit; }

    .table-card { overflow:hidden; margin-bottom:18px; }
    .table-card h2 { margin:0; padding:14px 18px 4px; font-size:14px; }
    .table-card .table-wrap { border:0; border-radius:0; margin:0; }
    .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }

    @media (max-width:1100px) { .kpis { grid-template-columns:repeat(2, 1fr); } }
    @media (max-width:600px) { .kpis { grid-template-columns:1fr; } }
@endsection

@section('sidebar')
    @include('partials.lider-sidebar')
@endsection

@section('content')
    @php
        $fmt = fn ($n, $d = 2) => number_format((float) $n, $d, '.', ',');
        $resumen = $reporte['resumen'];
        $grupos = $reporte['grupos'];
        $filas = $reporte['filas'];
    @endphp

    <h1 class="page-title">Reporte de Eficiencia de Tiempos</h1>
    <p class="page-sub">Contrasta los tiempos de revisión docente con la respuesta de la API de Gemini, filtrando por rango de fechas y asignatura</p>

    @if (session('error'))
        <div class="msg msg-err" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errores->any())
        <div class="msg msg-err" role="alert">
            <ul>@foreach ($errores->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="GET" action="{{ route('reportes.tiempos.index') }}" class="panel filters" aria-label="Filtros del reporte">
        <div class="field">
            <label for="desde">Fecha inicial</label>
            <input type="date" id="desde" name="desde" class="field-control" value="{{ $valores['desde'] ?? '' }}">
        </div>
        <div class="field">
            <label for="hasta">Fecha final</label>
            <input type="date" id="hasta" name="hasta" class="field-control" value="{{ $valores['hasta'] ?? '' }}">
        </div>
        <div class="field">
            <label for="asignatura">Asignatura</label>
            <select id="asignatura" name="asignatura" class="field-control">
                <option value="">Todas las asignaturas</option>
                @foreach ($asignaturas as $a)
                    <option value="{{ $a->id_asignatura }}" @selected((string) ($valores['asignatura'] ?? '') === (string) $a->id_asignatura)>{{ $a->nombre_asignatura }}</option>
                @endforeach
            </select>
        </div>
        <div class="buttons">
            <button type="submit" class="btn btn-navy">Generar reporte</button>
            <a href="{{ route('reportes.tiempos.index') }}" class="btn-ghost">Limpiar</a>
            @if ($resumen)
                <a href="{{ route('reportes.tiempos.excel', $consultaExcel) }}" class="btn btn-green btn-link">📊 Descargar Excel</a>
            @else
                <span class="btn btn-green btn-link" aria-disabled="true">📊 Descargar Excel</span>
            @endif
        </div>
    </form>
    <p class="hint">Las fechas corresponden al registro de la evaluación del docente. Solo se incluyen documentos que ya tienen evaluación del docente y de Gemini. La asignatura es la del grupo del equipo.</p>

    @if (! $resumen)
        @unless ($errores->any())
            <section class="panel"><p class="empty">No hay documentos con evaluación del docente y de Gemini con los filtros seleccionados.</p></section>
        @endunless
    @else
        <div class="kpis" aria-label="Resumen del reporte">
            <div class="panel kpi">
                <div class="kpi-label">Documentos comparados</div>
                <div class="kpi-value">{{ $resumen['documentos'] }}</div>
                <div class="kpi-note c-info">{{ $resumen['evidencias'] }} evidencia(s) · {{ $resumen['asignaturas'] }} asignatura(s)</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Revisión docente promedio</div>
                <div class="kpi-value">{{ $fmt($resumen['docente_prom_min']) }} min</div>
                <div class="kpi-note c-info">por documento</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Respuesta de la API promedio</div>
                <div class="kpi-value">{{ $fmt($resumen['ia_prom_min']) }} min</div>
                <div class="kpi-note c-info">≈ {{ $fmt($resumen['ia_prom_seg'], 1) }} segundos</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Tiempo ahorrado</div>
                <div class="kpi-value {{ $resumen['ahorro_pct'] >= 0 ? 'c-ok' : '' }}">{{ $fmt($resumen['ahorro_pct'], 1) }} %</div>
                <div class="kpi-note c-info">{{ $fmt($resumen['ahorro_prom_min']) }} min por documento</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Tiempo total</div>
                <div class="kpi-value">{{ $fmt($resumen['docente_total_horas']) }} h</div>
                <div class="kpi-note c-info">docente · API {{ $fmt($resumen['ia_total_horas']) }} h</div>
            </div>
        </div>

        @php
            // Gráfica de columnas comparativas (SVG): una pareja docente/API por evidencia, en minutos.
            $n = $grupos->count();
            $ancho = max(960, $n * 120 + 70);
            $alto = 360;
            $izq = 50; $der = 14; $arriba = 34; $abajo = 78;
            $plano = $alto - $arriba - $abajo;
            $ranura = ($ancho - $izq - $der) / $n;
            $columna = min(38, $ranura * 0.3);

            // Escala "redonda" (1, 2, 5 × 10^k) que contenga el mayor valor.
            $mayor = max($grupos->max('docente_min'), $grupos->max('ia_min'), 0.1);
            $exp = 10 ** floor(log10($mayor));
            $techo = $exp;
            foreach ([1, 2, 5, 10] as $paso) {
                $techo = $paso * $exp;
                if ($techo >= $mayor) { break; }
            }
            $y = fn ($valor) => $arriba + $plano * (1 - max(0, min($valor, $techo)) / $techo);
            $marcas = array_map(fn ($i) => $techo * $i / 5, range(0, 5));
            $recorta = fn (string $texto, int $max) => mb_strlen($texto) > $max ? rtrim(mb_substr($texto, 0, $max - 1)).'…' : $texto;
            $lineas = function (string $texto) use ($recorta) {
                $partes = explode("\n", wordwrap($texto, 17, "\n", true));
                $segunda = count($partes) > 2 ? $recorta($partes[1].' '.implode(' ', array_slice($partes, 2)), 17) : ($partes[1] ?? '');

                return [$recorta($partes[0], 17), $segunda];
            };
        @endphp
        <section class="panel chart-card" aria-labelledby="graficaTitulo">
            <h2 id="graficaTitulo">Tiempo de revisión docente vs. respuesta de la API por evidencia (promedio, minutos)</h2>
            <div class="legend">
                <span style="--c:#1e3a6e">Revisión docente</span>
                <span style="--c:#3f9a6b">Respuesta de la API</span>
                <span style="--c:transparent">Sobre cada par: porcentaje de tiempo ahorrado</span>
            </div>
            <div class="chart-scroll">
                <svg viewBox="0 0 {{ $ancho }} {{ $alto }}" style="min-width:{{ min($ancho, 720) }}px; width:100%" role="img"
                    aria-label="Gráfica de columnas comparativas del tiempo promedio de revisión del docente y de respuesta de la API por evidencia, en minutos. Los mismos datos aparecen en la tabla siguiente.">
                    @foreach ($marcas as $marca)
                        <line x1="{{ $izq }}" x2="{{ $ancho - $der }}" y1="{{ $y($marca) }}" y2="{{ $y($marca) }}" stroke="{{ $marca == 0 ? '#64748b' : '#e2e8f0' }}" stroke-width="1"/>
                        <text x="{{ $izq - 8 }}" y="{{ $y($marca) + 4 }}" text-anchor="end" font-size="11" fill="#64748b">{{ $fmt($marca, $techo < 5 ? 1 : 0) }}</text>
                    @endforeach

                    @foreach ($grupos as $i => $g)
                        @php
                            $centro = $izq + $ranura * ($i + 0.5);
                            $cima = $y(max($g['docente_min'], $g['ia_min']));
                        @endphp
                        <g>
                            <title>{{ $g['evidencia'] }} — {{ $g['asignatura'] }}: revisión docente {{ $fmt($g['docente_min']) }} min, respuesta de la API {{ $fmt($g['ia_min']) }} min, ahorro {{ $fmt($g['ahorro_pct'], 1) }} % ({{ $g['documentos'] }} documento(s))</title>
                            <rect x="{{ $centro - $columna - 1 }}" y="{{ $y($g['docente_min']) }}" width="{{ $columna }}" height="{{ $arriba + $plano - $y($g['docente_min']) }}" rx="3" fill="#1e3a6e"/>
                            <rect x="{{ $centro + 1 }}" y="{{ $y($g['ia_min']) }}" width="{{ $columna }}" height="{{ max(1.5, $arriba + $plano - $y($g['ia_min'])) }}" rx="2" fill="#3f9a6b"/>
                            <text x="{{ $centro - $columna / 2 - 1 }}" y="{{ $y($g['docente_min']) - 5 }}" text-anchor="middle" font-size="11" fill="#334155">{{ $fmt($g['docente_min']) }}</text>
                            <text x="{{ $centro + $columna / 2 + 1 }}" y="{{ $y($g['ia_min']) - 5 }}" text-anchor="middle" font-size="11" fill="#334155">{{ $fmt($g['ia_min']) }}</text>
                            <text x="{{ $centro }}" y="{{ max(14, $cima - 22) }}" text-anchor="middle" font-size="12" font-weight="700" fill="#15803d">Ahorro {{ $fmt($g['ahorro_pct'], 1) }} %</text>
                            @foreach ($lineas($g['evidencia']) as $k => $linea)
                                @if ($linea !== '')
                                    <text x="{{ $centro }}" y="{{ $arriba + $plano + 18 + $k * 14 }}" text-anchor="middle" font-size="11.5" fill="#0f172a">{{ $linea }}</text>
                                @endif
                            @endforeach
                            <text x="{{ $centro }}" y="{{ $arriba + $plano + 50 }}" text-anchor="middle" font-size="10.5" fill="#64748b" font-style="italic">{{ $recorta($g['asignatura'], 22) }}</text>
                        </g>
                    @endforeach
                </svg>
            </div>
        </section>

        <section class="panel table-card" aria-label="Resumen por evidencia">
            <h2>Resumen por evidencia</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Evidencia</th>
                            <th scope="col">Asignatura</th>
                            <th scope="col" class="num">Documentos</th>
                            <th scope="col" class="num">Docente (min)</th>
                            <th scope="col" class="num">API (min)</th>
                            <th scope="col" class="num">Ahorro (min)</th>
                            <th scope="col" class="num">Ahorro %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grupos as $g)
                            <tr>
                                <td><strong>{{ $g['evidencia'] }}</strong></td>
                                <td>{{ $g['asignatura'] }}</td>
                                <td class="num">{{ $g['documentos'] }}</td>
                                <td class="num">{{ $fmt($g['docente_min']) }}</td>
                                <td class="num">{{ $fmt($g['ia_min']) }}</td>
                                <td class="num">{{ $fmt($g['ahorro_min']) }}</td>
                                <td class="num">{{ $fmt($g['ahorro_pct'], 1) }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel table-card" aria-label="Detalle por documento">
            <h2>Detalle por documento</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Equipo</th>
                            <th scope="col">Evidencia</th>
                            <th scope="col">Asignatura</th>
                            <th scope="col">Evaluación docente</th>
                            <th scope="col" class="num">Docente (min)</th>
                            <th scope="col" class="num">API (seg)</th>
                            <th scope="col" class="num">Ahorro (min)</th>
                            <th scope="col" class="num">Ahorro %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $f)
                            <tr>
                                <td><strong>{{ $f['equipo'] }}</strong></td>
                                <td>{{ $f['evidencia'] }}</td>
                                <td>{{ $f['asignatura'] }}</td>
                                <td>{{ $f['fecha']?->format('d/m/Y H:i') }}</td>
                                <td class="num">{{ $fmt($f['docente_min']) }}</td>
                                <td class="num">{{ $fmt($f['ia_seg'], 1) }}</td>
                                <td class="num">{{ $fmt($f['ahorro_min']) }}</td>
                                <td class="num">{{ $fmt($f['ahorro_pct'], 1) }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
