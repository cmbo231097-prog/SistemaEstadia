@if ($paginator->hasPages())
    <nav class="pager" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="pg disabled">« Anterior</span>
        @else
            <a class="pg" href="{{ $paginator->previousPageUrl() }}" rel="prev">« Anterior</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pg gap">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pg current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pg" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="pg" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente »</a>
        @else
            <span class="pg disabled">Siguiente »</span>
        @endif
    </nav>
@endif
