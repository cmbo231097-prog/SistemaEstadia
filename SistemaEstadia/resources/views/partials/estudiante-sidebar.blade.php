@php
    // Menú reducido del estudiante: solo consulta, sin módulos de gestión ni carga.
    // Solo el panel tiene ruta por ahora; el resto de módulos se conectarán más adelante.
    $menu = [
        'Consulta Estudiantil' => [
            ['icon' => '📊', 'label' => 'Panel de Calificaciones', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
            ['icon' => '👥', 'label' => 'Mi Equipo y Materias'],
        ],
        'Reportes y Contraste' => [
            ['icon' => '📄', 'label' => 'Mi Reporte de Contraste (PDF)', 'route' => 'contraste.index', 'active' => request()->routeIs('contraste.*')],
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
