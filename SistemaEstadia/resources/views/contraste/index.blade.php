@extends('layouts.panel')

@section('title', 'Mi Reporte de Contraste')

@section('styles')
    @include('partials.dashboard-styles')

    .alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; border:1px solid; }
    .alert-err { background:var(--bad-bg); color:var(--bad-fg); border-color:#f3b4b4; }
    .alert-warn { background:var(--warn-bg); color:var(--warn-fg); border-color:#f1d28a; }

    .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; }
    .selectors { display:grid; grid-template-columns:repeat(2, 1fr); gap:14px; padding:14px 16px; margin-bottom:16px; }
    .field-control {
        width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:8px;
        background-color:#fff; font-family:inherit; font-size:12.5px; color:var(--text);
    }
    .selectors select:last-child { border-color:var(--blue); color:var(--blue); font-weight:700; }
    .btn-link { display:inline-block; text-decoration:none; text-align:center; flex:0 0 auto; max-width:none; padding:11px 18px; }
    .btn[aria-disabled="true"] { opacity:.5; cursor:not-allowed; pointer-events:none; }
    .hint { font-size:12px; color:var(--muted); margin:-6px 0 16px; }
    .context { font-size:13px; color:var(--muted); margin:0 0 16px; }
    .context strong { color:var(--text); }
    .c-info { color:var(--info-fg); } .c-ok { color:var(--ok-fg); }
    .kpi-value { font-size:24px; }

    .chart-card { padding:16px 18px 12px; margin-bottom:18px; }
    .chart-card h2 { margin:0 0 4px; font-size:14px; }
    .chart-card > p { margin:0 0 6px; font-size:12px; color:var(--muted); }
    .legend { display:flex; flex-wrap:wrap; gap:16px; font-size:12px; color:var(--muted); margin:0 0 8px; }
    .legend span::before { content:''; display:inline-block; width:11px; height:11px; border-radius:3px; margin-right:6px; vertical-align:-1px; background:var(--c); }
    .chart-scroll { overflow-x:auto; }
    .chart-scroll svg { display:block; height:auto; }
    .chart-scroll text { font-family:inherit; }

    .table-card { overflow:hidden; margin-bottom:18px; }
    .table-card h2 { margin:0; padding:14px 18px 4px; font-size:14px; }
    .table-card .table-wrap { border:0; border-radius:0; margin:0; }
    table { min-width:820px; }
    .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .tag { display:inline-block; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; }
    .tag-doc { background:var(--warn-bg); color:var(--warn-fg); }
    .muted { color:var(--muted); }
    .comments { font-size:12px; white-space:pre-line; }
    .comments b { display:block; margin-top:4px; font-size:11px; }
    .comments b:first-child { margin-top:0; }
    .comments .d { color:var(--navy); } .comments .i { color:var(--ok-fg); }
    .general { display:grid; grid-template-columns:repeat(2, 1fr); gap:16px; margin-bottom:18px; }
    .general .panel { padding:16px 18px; }
    .general h3 { margin:0 0 6px; font-size:13px; }
    .general p { margin:0; font-size:12.5px; line-height:1.55; white-space:pre-line; }

    @media (max-width:760px) { .selectors, .general { grid-template-columns:1fr; } }
@endsection

@section('sidebar')
    @include('partials.estudiante-sidebar')
@endsection

@section('content')
    @php
        $fmt = fn ($n, $d = 2) => rtrim(rtrim(number_format((float) $n, $d, '.', ','), '0'), '.') ?: '0';
        $sd = fn ($n) => $n === null ? '—' : $fmt($n);
        $completo = $reporte && $reporte['estado'] === \App\Services\ContrasteIndividualService::COMPLETO;
    @endphp

    <div class="page-head">
        <div>
            <h1 class="page-title">Mi Reporte de Contraste Evaluativo</h1>
            <p class="page-sub">Compara, rubro por rubro, la calificación de tu profesor con la emitida por la IA (Gemini) para el documento de tu equipo</p>
        </div>
        @if ($completo)
            <a href="{{ route('contraste.pdf', $consultaPdf) }}" class="btn btn-navy btn-link">📄 Descargar PDF</a>
        @else
            <span class="btn btn-navy btn-link" aria-disabled="true">📄 Descargar PDF</span>
        @endif
    </div>

    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif

    @if ($asignaturas->isEmpty())
        <section class="panel"><p class="empty">Aún no perteneces a un equipo de ninguna asignatura. Cuando tu profesor te asigne a uno, podrás consultar aquí el contraste de tus evidencias.</p></section>
    @else
        <form method="GET" action="{{ route('contraste.index') }}" class="panel selectors" aria-label="Filtros del reporte">
            <select name="asignatura" class="field-control" aria-label="Asignatura" onchange="this.form.evidencia.value='';this.form.submit()">
                @foreach ($asignaturas as $a)
                    <option value="{{ $a->id_asignatura }}" @selected($asignatura?->id_asignatura === $a->id_asignatura)>{{ $a->nombre_asignatura }}</option>
                @endforeach
            </select>
            <select name="evidencia" class="field-control" aria-label="Evidencia" onchange="this.form.submit()">
                @forelse ($evidencias as $e)
                    <option value="{{ $e->id_evidencia }}" @selected($evidencia?->id_evidencia === $e->id_evidencia)>{{ $e->titulo }}</option>
                @empty
                    <option value="">Sin evidencias para esta asignatura</option>
                @endforelse
            </select>
        </form>

        @if ($equipo)
            <p class="context">Equipo <strong>{{ $equipo->nombre_equipo }}</strong> · Grupo <strong>{{ $equipo->grupo->clave_grupo }}</strong>
                @if ($equipo->grupo->docente) · Profesor <strong>{{ $equipo->grupo->docente->nombre }} {{ $equipo->grupo->docente->apellidos }}</strong>@endif</p>
        @endif

        @if (! $reporte)
            <section class="panel"><p class="empty">Esta asignatura aún no tiene evidencias asignadas a tu grupo.</p></section>
        @else
            @php
                $r = $reporte['resumen'];
                $rubros = $reporte['rubros'];
                $maxIa = $fmt($r['ia_maximo']);
            @endphp

            @switch($reporte['estado'])
                @case('sin_documento')
                    <div class="alert alert-warn" role="status">Tu equipo todavía no tiene un documento cargado para esta evidencia, por lo que aún no hay calificaciones que contrastar.</div>
                    @break
                @case('sin_evaluaciones')
                    <div class="alert alert-warn" role="status">El documento de tu equipo aún no ha sido evaluado por el profesor ni por la IA.</div>
                    @break
                @case('sin_docente')
                    <div class="alert alert-warn" role="status">La IA ya evaluó tu documento, pero tu profesor aún no registra su calificación. El contraste estará completo cuando lo haga.</div>
                    @break
                @case('sin_ia')
                    <div class="alert alert-warn" role="status">Tu profesor ya calificó tu documento, pero aún no hay evaluación de la IA para contrastar.</div>
                    @break
            @endswitch

            @if ($reporte['docente'] || $reporte['ia'])
                <div class="kpis" aria-label="Resumen de calificaciones">
                    <div class="panel kpi">
                        <div class="kpi-label">Calificación del profesor</div>
                        <div class="kpi-value">{{ $sd($r['docente_total']) }}</div>
                        <div class="kpi-note c-info">sobre 100</div>
                    </div>
                    <div class="panel kpi">
                        <div class="kpi-label">Calificación de la IA</div>
                        <div class="kpi-value">{{ $sd($r['ia_total']) }}</div>
                        <div class="kpi-note c-info">sobre {{ $maxIa }} (rubros que evalúa la IA)</div>
                    </div>
                    <div class="panel kpi">
                        <div class="kpi-label">Profesor en los rubros de la IA</div>
                        <div class="kpi-value">{{ $sd($r['docente_en_rubros_ia']) }}</div>
                        <div class="kpi-note c-info">sobre {{ $maxIa }} · mismos rubros</div>
                    </div>
                    <div class="panel kpi">
                        <div class="kpi-label">Diferencia (IA − profesor)</div>
                        <div class="kpi-value">{{ $r['diferencia'] === null ? '—' : ($r['diferencia'] > 0 ? '+' : '').$fmt($r['diferencia']) }}</div>
                        <div class="kpi-note c-info">{{ $r['diferencia_pct'] === null ? 'en los mismos rubros' : $fmt($r['diferencia_pct'], 1).' % frente al profesor' }}</div>
                    </div>
                </div>

                @php
                    // Gráfica de barras horizontales (SVG): profesor e IA por rubro, como % de la calificación máxima del rubro.
                    $filas = collect();
                    if ($r['docente_en_rubros_ia'] !== null && $r['ia_maximo'] > 0) {
                        $filas->push(['nombre' => 'TOTAL (rubros que evalúa la IA)', 'total' => true,
                            'd' => $r['docente_en_rubros_ia'] / $r['ia_maximo'] * 100, 'i' => $r['ia_total'] / $r['ia_maximo'] * 100,
                            'dt' => $fmt($r['docente_en_rubros_ia']).' / '.$maxIa, 'it' => $fmt($r['ia_total']).' / '.$maxIa]);
                    }
                    foreach ($rubros as $b) {
                        $filas->push(['nombre' => $b['nombre'], 'total' => false, 'd' => $b['docente_pct'], 'i' => $b['ia_pct'],
                            'dt' => $b['docente'] === null ? '—' : $fmt($b['docente']).' / '.$fmt($b['maximo']),
                            'it' => $b['ia'] === null ? ($b['ia_evalua'] ? '—' : 'no la evalúa la IA') : $fmt($b['ia']).' / '.$fmt($b['maximo'])]);
                    }
                    $ancho = 940; $izq = 250; $plano = 500; $arriba = 30; $alto = 62;
                    $altoTotal = $arriba + $filas->count() * $alto + 12;
                    // Hasta 2 líneas; si el nombre es más largo, la segunda termina en puntos suspensivos.
                    $envolver = function (string $t) {
                        $lineas = explode("\n", wordwrap($t, 34, "\n", true));
                        if (count($lineas) <= 2) {
                            return $lineas;
                        }
                        $resto = implode(' ', array_slice($lineas, 1));

                        return [$lineas[0], mb_strlen($resto) > 33 ? rtrim(mb_substr($resto, 0, 32)).'…' : $resto];
                    };
                @endphp
                <section class="panel chart-card" aria-labelledby="graficaTitulo">
                    <h2 id="graficaTitulo">Contraste por rubro: profesor vs. IA</h2>
                    <p>La longitud de cada barra es el porcentaje de la calificación máxima de cada rubro.</p>
                    <div class="legend">
                        <span style="--c:#1e3a6e">Profesor</span>
                        <span style="--c:#3f9a6b">IA (Gemini)</span>
                    </div>
                    <div class="chart-scroll">
                        <svg viewBox="0 0 {{ $ancho }} {{ $altoTotal }}" style="min-width:640px; width:100%" role="img"
                            aria-label="Gráfica de barras horizontales que compara, por rubro, la calificación del profesor y la de la IA. Los mismos datos aparecen en la tabla siguiente.">
                            @foreach ([0, 25, 50, 75, 100] as $marca)
                                <line x1="{{ $izq + $plano * $marca / 100 }}" x2="{{ $izq + $plano * $marca / 100 }}" y1="{{ $arriba - 6 }}" y2="{{ $altoTotal - 8 }}" stroke="{{ $marca === 0 ? '#64748b' : '#e2e8f0' }}" stroke-width="1"/>
                                <text x="{{ $izq + $plano * $marca / 100 }}" y="{{ $arriba - 12 }}" text-anchor="middle" font-size="11" fill="#64748b">{{ $marca }} %</text>
                            @endforeach

                            @foreach ($filas as $k => $f)
                                @php $y0 = $arriba + $k * $alto; @endphp
                                <g>
                                    @if ($f['total'])
                                        <rect x="0" y="{{ $y0 - 2 }}" width="{{ $ancho }}" height="{{ $alto - 4 }}" fill="#f1f5f9" rx="6"/>
                                    @endif
                                    @foreach ($envolver($f['nombre']) as $n => $linea)
                                        <text x="8" y="{{ $y0 + 22 + $n * 15 }}" font-size="12.5" font-weight="{{ $f['total'] ? 700 : 500 }}" fill="#0f172a">{{ $linea }}</text>
                                    @endforeach
                                    @foreach ([['#1e3a6e', $f['d'], $f['dt'], 4, 'Profesor'], ['#3f9a6b', $f['i'], $f['it'], 26, 'IA']] as [$color, $pct, $texto, $dy, $quien])
                                        @if ($pct !== null)
                                            <rect x="{{ $izq }}" y="{{ $y0 + $dy }}" width="{{ max(2, $plano * min($pct, 100) / 100) }}" height="17" rx="3" fill="{{ $color }}">
                                                <title>{{ $f['nombre'] }} · {{ $quien }}: {{ $texto }} ({{ $fmt($pct, 1) }} %)</title>
                                            </rect>
                                        @endif
                                        <text x="{{ $izq + $plano + 10 }}" y="{{ $y0 + $dy + 13 }}" font-size="12" fill="{{ $pct === null ? '#94a3b8' : '#334155' }}" font-style="{{ $pct === null ? 'italic' : 'normal' }}">{{ $texto }}</text>
                                    @endforeach
                                </g>
                            @endforeach
                        </svg>
                    </div>
                </section>

                <section class="panel table-card" aria-label="Detalle por rubro">
                    <h2>Detalle por rubro</h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Rubro</th>
                                    <th scope="col" class="num">Máximo</th>
                                    <th scope="col" class="num">Profesor</th>
                                    <th scope="col" class="num">IA</th>
                                    <th scope="col" class="num">Dif. (IA − profesor)</th>
                                    <th scope="col">Retroalimentación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rubros as $b)
                                    <tr>
                                        <td>
                                            <strong>{{ $b['nombre'] }}</strong>
                                            @unless ($b['ia_evalua'])<br><span class="tag tag-doc">Solo lo califica el profesor</span>@endunless
                                        </td>
                                        <td class="num">{{ $fmt($b['maximo']) }}</td>
                                        <td class="num">{{ $sd($b['docente']) }}</td>
                                        <td class="num">{{ $b['ia_evalua'] ? $sd($b['ia']) : '—' }}</td>
                                        <td class="num">{{ $b['diferencia'] === null ? '—' : ($b['diferencia'] > 0 ? '+' : '').$fmt($b['diferencia']) }}</td>
                                        <td class="comments">
                                            @if ($b['retro_docente'])<b class="d">Profesor</b>{{ $b['retro_docente'] }}@endif
                                            @if ($b['retro_ia'])<b class="i">IA</b>{{ $b['retro_ia'] }}@endif
                                            @if (! $b['retro_docente'] && ! $b['retro_ia'])<span class="muted">—</span>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                @if (filled($reporte['docente']['comentarios'] ?? null) || filled($reporte['ia']['comentarios'] ?? null))
                    <div class="general">
                        @if (filled($reporte['docente']['comentarios'] ?? null))
                            <section class="panel"><h3>Comentarios generales del profesor</h3><p>{{ $reporte['docente']['comentarios'] }}</p></section>
                        @endif
                        @if (filled($reporte['ia']['comentarios'] ?? null))
                            <section class="panel"><h3>Retroalimentación general de la IA</h3><p>{{ $reporte['ia']['comentarios'] }}</p></section>
                        @endif
                    </div>
                @endif
            @endif
        @endif
    @endif
@endsection
