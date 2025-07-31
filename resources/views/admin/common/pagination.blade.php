@if ($paginator->hasPages())
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center mb-0">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link bg-light text-muted border-secondary">&laquo;</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link text-primary border-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo;</a>
                </li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li class="page-item disabled">
                        <span class="page-link bg-light text-muted border-secondary">{{ $element }}</span>
                    </li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active">
                                <span class="page-link bg-primary border-primary text-white">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link text-dark border-secondary" href="{{ $url }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link text-primary border-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">&raquo;</a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link bg-light text-muted border-secondary">&raquo;</span>
                </li>
            @endif
        </ul>
    </nav>
@endif