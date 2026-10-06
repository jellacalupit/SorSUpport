{{-- Page numbers are shown ten at a time (1–10, 11–20, …) so the row never grows past ten numbers. --}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $groupStart = (int) (floor(($current - 1) / 10) * 10) + 1;
        $groupEnd = min($groupStart + 9, $last);
        $arrowClass = 'grid h-8 w-8 place-items-center rounded-md border border-border bg-card text-sm text-foreground transition-colors hover:bg-primary-soft hover:text-primary';
        $disabledArrowClass = 'grid h-8 w-8 cursor-not-allowed place-items-center rounded-md border border-border bg-muted text-sm text-muted-foreground opacity-60';
    @endphp
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-muted-foreground">
            Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}
        </p>

        <div class="flex items-center gap-1">
            {{-- Previous group of ten --}}
            @if ($groupStart > 1)
                <a href="{{ $paginator->url($groupStart - 1) }}" class="{{ $arrowClass }} hidden sm:grid" aria-label="Previous 10 pages" title="Previous 10 pages">&laquo;</a>
            @endif

            {{-- Previous page --}}
            @if ($paginator->onFirstPage())
                <span class="{{ $disabledArrowClass }}" aria-disabled="true" aria-label="Previous page">&lsaquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $arrowClass }}" aria-label="Previous page">&lsaquo;</a>
            @endif

            {{-- Phones: just the position --}}
            <span class="px-2 text-xs font-semibold text-foreground sm:hidden">Page {{ $current }} of {{ $last }}</span>

            {{-- The current group of up to ten page numbers --}}
            @for ($page = $groupStart; $page <= $groupEnd; $page++)
                @if ($page === $current)
                    <span aria-current="page" class="hidden h-8 min-w-8 place-items-center rounded-md bg-primary px-2 text-xs font-bold text-primary-foreground sm:grid">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="hidden h-8 min-w-8 place-items-center rounded-md border border-border bg-card px-2 text-xs font-medium text-foreground transition-colors hover:bg-primary-soft hover:text-primary sm:grid" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                @endif
            @endfor

            {{-- Next page --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $arrowClass }}" aria-label="Next page">&rsaquo;</a>
            @else
                <span class="{{ $disabledArrowClass }}" aria-disabled="true" aria-label="Next page">&rsaquo;</span>
            @endif

            {{-- Next group of ten --}}
            @if ($groupEnd < $last)
                <a href="{{ $paginator->url($groupEnd + 1) }}" class="{{ $arrowClass }} hidden sm:grid" aria-label="Next 10 pages" title="Next 10 pages">&raquo;</a>
            @endif
        </div>
    </nav>
@endif
