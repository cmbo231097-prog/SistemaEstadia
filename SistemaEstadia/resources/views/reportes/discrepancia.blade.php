@extends('layouts.panel')

@section('title', 'Reporte de Discrepancia')

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
    .btn-link { display:inline-block; text-decoration:none; text-align:center; max-width:none; }
    .btn-ghost { background:#fff; border:1px solid var(--border); color:#334155; border-radius:8px; padding:10px 16px; font-size:12.5px; font-weight:700; text-decoration:none; display:inline-block; }
    .btn-ghost:hover { background:var(--idle-bg); }
    .btn:disabled, .btn[aria-disabled="true"] { opacity:.5; cursor:not-allowed; pointer-events:none; }
    .hint { font-size:12px; color:var(--muted); margin:-6px 0 16px; }
    .c-ok { color:var(--ok-fg); } .c-bad { color:var(--bad-fg); } .c-info { color:var(--info-fg); }
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
    .over { color:var(--bad-fg); font-weight:700; }

    @media (max-width:1100px) { .kpis { grid-template-columns:repeat(2, 1fr); } }
    @media (max-width:600px) { .kpis { grid-template-columns:1fr; } }
@endsection

@section('sidebar')
    @include('partials.lider-sidebar')
@endsection

@section('content')
    @php
        $fmt = fn ($n, $d = 1) => number_format((float) $n, $d, '.', ',');
        $resumen = $reporte['resumen'];
        $grupos = $reporte['grupos'];
        $filas = $reporte['filas'];
    @endphp

    <h1 class="page-title">Reporte de Discrepancia Evaluativa</h1>
    <p class="page-sub">Compara la diferencia porcentual entre las calificaciones del docente y las emitidas por Google Gemini, filtrando por rango de fechas y profesor</p>

    @if (session('error'))
        <div class="msg msg-err" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errores->any())
        <div class="msg msg-err" role="alert">
            <ul>@foreach ($errores->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="GET" action="{{ route('reportes.discrepancia.index') }}" class="panel filters" aria-label="Filtros del reporte">
        <div class="field">
            <label for="desde">Fecha inicial</label>
            <input type="date" id="desde" name="desde" class="field-control" value="{{ $valores['desde'] ?? '' }}">
        </div>
        <div class="field">
            <label for="hasta">Fecha final</label>
            <input type="date" id="hasta" name="hasta" class="field-control" value="{{ $valores['hasta'] ?? '' }}">
        </div>
        <div class="field">
            <label for="profesor">Profesor</label>
            <select id="profesor" name="profesor" class="field-control">
                <option value="">Todos los profesores</option>
                @foreach ($profesores as $p)
                    <option value="{{ $p->id_usuario }}" @selected((string) ($valores['profesor'] ?? '') === (string) $p->id_usuario)>{{ $p->nombre }} {{ $p->apellidos }}</option>
                @endforeach
            </select>
        </div>
        <div class="buttons">
            <button type="submit" class="btn btn-navy">Generar reporte</button>
            <a href="{{ route('reportes.discrepancia.index') }}" class="btn-ghost">Limpiar</a>
            @if ($resumen)
                <a href="{{ route('reportes.discrepancia.pdf', $consultaPdf) }}" class="btn btn-green btn-link">📄 Descargar PDF</a>
            @else
                <span class="btn btn-green btn-link" aria-disabled="true">📄 Descargar PDF</span>
            @endif
        </div>
    </form>
    <p class="hint">Las fechas corresponden al registro de la evaluación del docente. Solo se incluyen documentos que ya tienen evaluación de Gemini y del docente (calculados en el Motor Comparativo).</p>

    @if (! $resumen)
        @unless ($errores->any())
            <section class="panel"><p class="empty">No hay comparaciones entre el docente y Gemini con los filtros seleccionados.</p></section>
        @endunless
    @else
        <div class="kpis" aria-label="Resumen del reporte">
            <div class="panel kpi">
                <div class="kpi-label">Documentos comparados</div>
                <div class="kpi-value">{{ $resumen['documentos'] }}</div>
                <div class="kpi-note c-info">{{ $resumen['evidencias'] }} evidencia(s) · {{ $resumen['profesores'] }} profesor(es)</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Calificación promedio docente</div>
                <div class="kpi-value">{{ $fmt($resumen['docente_promedio']) }}</div>
                <div class="kpi-note c-info">sobre 100</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Calificación promedio Gemini</div>
                <div class="kpi-value">{{ $fmt($resumen['ia_promedio']) }}</div>
                <div class="kpi-note c-info">sobre 100</div>
            </div>
            <div class="panel kpi">
                <div class="kpi-label">Diferencia porcentual promedio</div>
                <div class="kpi-value">{{ $fmt($resumen['margen_promedio']) }} %</div>
                <div class="kpi-note c-info">máxima {{ $fmt($resumen['margen_maximo']) }} %</div>
            </div>
            <div class="panel kpi {{ $resumen['alertas'] > 0 ? 'alert' : '' }}">
                <div class="kpi-label">Documentos con alerta</div>
                <div class="kpi-value">{{ $resumen['alertas'] }}</div>
                <div class="kpi-note {{ $resumen['alertas'] > 0 ? 'c-bad' : 'c-ok' }}">diferencia mayor a {{ $fmt($umbral, 0) }} %</div>
            </div>
        </div>

        @php
            // Gráfica de barras comparativas (SVG): una pareja docente/Gemini por evidencia.
            $n = $grupos->count();
            $ancho = max(960, $n * 120 + 70);
            $alto = 360;
            $izq = 46; $der = 14; $arriba = 34; $abajo = 78;
            $plano = $alto - $arriba - $abajo;
            $ranura = ($ancho - $izq - $der) / $n;
            $barra = min(38, $ranura * 0.3);
            $y = fn ($valor) => $arriba + $plano * (1 - max(0, min($valor, 100)) / 100);
            $recorta = function (string $texto, int $max) {
                return mb_strlen($texto) > $max ? rtrim(mb_substr($texto, 0, $max - 1)).'…' : $texto;
            };
            $lineas = function (string $texto) use ($recorta) {
                $partes = explode("\n", wordwrap($texto, 17, "\n", true));
                $primera = $partes[0];
                $segunda = count($partes) > 2 ? $recorta($partes[1].' '.implode(' ', array_slice($partes, 2)), 17) : ($partes[1] ?? '');

                return [$recorta($primera, 17), $segunda];
            };
        @endphp
        <section class="panel chart-card" aria-labelledby="graficaTitulo">
            <h2 id="graficaTitulo">Calificación del docente vs. Gemini por evidencia (promedio, sobre 100)</h2>
            <div class="legend">
                <span style="--c:#1e3a6e">Docente</span>
                <span style="--c:#3f9a6b">Gemini</span>
                <span style="--c:transparent">Sobre cada par: diferencia porcentual frente a la calificación del docente</span>
            </div>
            <div class="chart-scroll">
                <svg viewBox="0 0 {{ $ancho }} {{ $alto }}" style="min-width:{{ min($ancho, 720) }}px; width:100%" role="img"
                    aria-label="Gráfica de barras comparativas de la calificación promedio del docente y de Gemini por evidencia. Los mismos datos aparecen en la tabla siguiente.">
                    @foreach ([0, 20, 40, 60, 80, 100] as $marca)
                        <line x1="{{ $izq }}" x2="{{ $ancho - $der }}" y1="{{ $y($marca) }}" y2="{{ $y($marca) }}" stroke="{{ $marca === 0 ? '#64748b' : '#e2e8f0' }}" stroke-width="1"/>
                        <text x="{{ $izq - 8 }}" y="{{ $y($marca) + 4 }}" text-anchor="end" font-size="11" fill="#64748b">{{ $marca }}</text>
                    @endforeach

                    @foreach ($grupos as $i => $g)
                        @php
                            $centro = $izq + $ranura * ($i + 0.5);
                            $alerta = $g['diferencia_pct'] > $umbral;
                            $techo = $y(max($g['docente'], $g['ia']));
                        @endphp
                        <g>
                            <title>{{ $g['evidencia'] }} — {{ $g['profesor'] }}: docente {{ $fmt($g['docente']) }}, Gemini {{ $fmt($g['ia']) }}, diferencia {{ $fmt($g['diferencia_pct']) }} % ({{ $g['documentos'] }} documento(s))</title>
                            <rect x="{{ $centro - $barra - 1 }}" y="{{ $y($g['docente']) }}" width="{{ $barra }}" height="{{ $arriba + $plano - $y($g['docente']) }}" rx="3" fill="#1e3a6e"/>
                            <rect x="{{ $centro + 1 }}" y="{{ $y($g['ia']) }}" width="{{ $barra }}" height="{{ $arriba + $plano - $y($g['ia']) }}" rx="3" fill="#3f9a6b"/>
                            <text x="{{ $centro - $barra / 2 - 1 }}" y="{{ $y($g['docente']) - 5 }}" text-anchor="middle" font-size="11" fill="#334155">{{ $fmt($g['docente']) }}</text>
                            <text x="{{ $centro + $barra / 2 + 1 }}" y="{{ $y($g['ia']) - 5 }}" text-anchor="middle" font-size="11" fill="#334155">{{ $fmt($g['ia']) }}</text>
                            <text x="{{ $centro }}" y="{{ max(14, $techo - 22) }}" text-anchor="middle" font-size="12" font-weight="700" fill="{{ $alerta ? '#b91c1c' : '#1e3a6e' }}">Dif. {{ $fmt($g['diferencia_pct']) }} %</text>
                            @foreach ($lineas($g['evidencia']) as $k => $linea)
                                @if ($linea !== '')
                                    <text x="{{ $centro }}" y="{{ $arriba + $plano + 18 + $k * 14 }}" text-anchor="middle" font-size="11.5" fill="#0f172a">{{ $linea }}</text>
                                @endif
                            @endforeach
                            <text x="{{ $centro }}" y="{{ $arriba + $plano + 50 }}" text-anchor="middle" font-size="10.5" fill="#64748b" font-style="italic">{{ $recorta($g['profesor'], 20) }}</text>
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
                            <th scope="col">Profesor</th>
                            <th scope="col" class="num">Documentos</th>
                            <th scope="col" class="num">Docente (/100)</th>
                            <th scope="col" class="num">Gemini (/100)</th>
                            <th scope="col" class="num">Diferencia %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grupos as $g)
                            <tr>
                                <td><strong>{{ $g['evidencia'] }}</strong></td>
                                <td>{{ $g['profesor'] }}</td>
                                <td class="num">{{ $g['documentos'] }}</td>
                                <td class="num">{{ $fmt($g['docente']) }}</td>
                                <td class="num">{{ $fmt($g['ia']) }}</td>
                                <td class="num {{ $g['diferencia_pct'] > $umbral ? 'over' : '' }}">{{ $fmt($g['diferencia_pct']) }} %</td>
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
                            <th scope="col">Profesor</th>
                            <th scope="col">Evaluación docente</th>
                            <th scope="col" class="num">Docente</th>
                            <th scope="col" class="num">Gemini</th>
                            <th scope="col" class="num">Dif. pts</th>
                            <th scope="col" class="num">Dif. %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $f)
                            <tr>
                                <td><strong>{{ $f['equipo'] }}</strong></td>
                                <td>{{ $f['evidencia'] }}</td>
                                <td>{{ $f['profesor'] }}</td>
                                <td>{{ $f['fecha']?->format('d/m/Y H:i') }}</td>
                                <td class="num">{{ $fmt($f['docente']) }}</td>
                                <td class="num">{{ $fmt($f['ia']) }}</td>
                                <td class="num">{{ $fmt($f['diferencia_pts']) }}</td>
                                <td class="num {{ $f['alerta'] ? 'over' : '' }}">{{ $fmt($f['diferencia_pct']) }} %{{ $f['alerta'] ? ' ⚠' : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
