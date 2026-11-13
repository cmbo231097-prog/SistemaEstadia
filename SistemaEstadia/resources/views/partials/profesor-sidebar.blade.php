@php
    // Menú exclusivo del docente: no incluye funciones administrativas.
    // Solo el panel tiene ruta por ahora; el resto de módulos se conectarán más adelante.
    $menu = [
        'Docencia & Grupos' => [
            ['icon' => '📊', 'label' => 'Panel de Control', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
            ['icon' => '👥', 'label' => 'Mis Grupos y Equipos', 'route' => 'equipos.index', 'active' => request()->routeIs('equipos.*')],
            ['icon' => '📝', 'label' => 'Gestión de Evidencias', 'route' => 'evidencias.index', 'active' => request()->routeIs('evidencias.*')],
        ],
        'Evaluación & Corpus' => [
            ['icon' => '📁', 'label' => 'Carga y Sanitización (PDF)', 'route' => 'corpus.index', 'active' => request()->routeIs('corpus.*')],
            ['icon' => '✍️', 'label' => 'Captura de Evaluación', 'route' => 'evaluacion-docente.index', 'active' => request()->routeIs('evaluacion-docente.*')],
            ['icon' => '🤖', 'label' => 'Evaluación con IA', 'route' => 'evaluacion-ia.index', 'active' => request()->routeIs('evaluacion-ia.*')],
            ['icon' => '⚖️', 'label' => 'Motor Comparativo', 'route' => 'motor-comparativo.index', 'active' => request()->routeIs('motor-comparativo.*')],
        ],
        'Reportes Académicos' => [
            ['icon' => '📈', 'label' => 'Rendimiento Grupal (.xlsx)', 'route' => 'rendimiento-grupal.index', 'active' => request()->routeIs('rendimiento-grupal.*')],
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
