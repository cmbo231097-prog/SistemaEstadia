@php
    // Solo los módulos con ruta están enlazados; el resto se conectarán más adelante.
    $menu = [
        'Monitoreo & Experimento' => [
            ['icon' => '📊', 'label' => 'Dashboard General', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
            ['icon' => '📁', 'label' => 'Carga y Sanitización', 'route' => 'corpus.index', 'active' => request()->routeIs('corpus.*')],
            ['icon' => '✍️', 'label' => 'Captura de Evaluación', 'route' => 'evaluacion-docente.index', 'active' => request()->routeIs('evaluacion-docente.*')],
            ['icon' => '🤖', 'label' => 'Evaluación con IA', 'route' => 'evaluacion-ia.index', 'active' => request()->routeIs('evaluacion-ia.*')],
            ['icon' => '⚖️', 'label' => 'Motor Comparativo', 'route' => 'motor-comparativo.index', 'active' => request()->routeIs('motor-comparativo.*')],
        ],
        'Reportes Gerenciales' => [
            ['icon' => '📄', 'label' => 'Reporte de Discrepancia (PDF)', 'route' => 'reportes.discrepancia.index', 'active' => request()->routeIs('reportes.discrepancia.*')],
            ['icon' => '⏱️', 'label' => 'Eficiencia de Tiempos (XLSX)', 'route' => 'reportes.tiempos.index', 'active' => request()->routeIs('reportes.tiempos.*')],
            ['icon' => '📈', 'label' => 'Rendimiento Grupal (XLSX)', 'route' => 'rendimiento-grupal.index', 'active' => request()->routeIs('rendimiento-grupal.*')],
        ],
        'Administración & Base de Datos' => [
            ['icon' => '👥', 'label' => 'Gestión de Usuarios', 'route' => 'admin.usuarios.index', 'active' => request()->routeIs('admin.usuarios.*')],
            ['icon' => '📚', 'label' => 'Gestión de Asignaturas', 'route' => 'admin.asignaturas.index', 'active' => request()->routeIs('admin.asignaturas.*')],
            ['icon' => '🧑‍🏫', 'label' => 'Gestión de Grupos', 'route' => 'admin.grupos.index', 'active' => request()->routeIs('admin.grupos.*')],
            ['icon' => '🧩', 'label' => 'Gestión de Equipos', 'route' => 'equipos.index', 'active' => request()->routeIs('equipos.*')],
            ['icon' => '📝', 'label' => 'Gestión de Evidencias', 'route' => 'evidencias.index', 'active' => request()->routeIs('evidencias.*')],
            ['icon' => '💾', 'label' => 'Respaldo de BD (SQL)', 'route' => 'respaldos.index', 'active' => request()->routeIs('respaldos.*')],
        ],
    ];
@endphp

<nav>
    @foreach ($menu as $section => $items)
        <div class="nav-title">{{ $section }}</div>
        @foreach ($items as $item)
            @if (isset($item['route']))
                <a href="{{ route($item['route']) }}" class="nav-link {{ ($item['active'] ?? false) ? 'active' : '' }}"
                    @if ($item['active'] ?? false) aria-current="page" @endif>
                    <span class="nav-icon" aria-hidden="true">{{ $item['icon'] }}</span>{{ $item['label'] }}
                </a>
            @else
                <a href="#" class="nav-link" data-soon="{{ $item['label'] }}">
                    <span class="nav-icon" aria-hidden="true">{{ $item['icon'] }}</span>{{ $item['label'] }}
                </a>
            @endif
        @endforeach
    @endforeach
</nav>
