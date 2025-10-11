@if ($paginator->hasPages())
    <nav role="navigation" aria-label="صفحه

">
        <ul class="pagination" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;list-style-type: none;">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="disabled" aria-disabled="true" aria-label="قبلی">
                    <span style="opacity:0.5;cursor:not-allowed;">قبلی</span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="قبلی" style="padding:8px 14px;border-radius:8px;background:var(--primary-color);color:#fff;text-decoration:none;">قبلی</a>
                </li>
            @endif

            {{-- Page Info --}}
            <li class="page-info" style="min-width:140px;text-align:center;">
                صفحه {{ $paginator->currentPage() }} از {{ $paginator->lastPage() }}
            </li>

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="بعدی" style="padding:8px 14px;border-radius:8px;background:var(--primary-color);color:#fff;text-decoration:none;">بعدی</a>
                </li>
            @else
                <li class="disabled" aria-disabled="true" aria-label="بعدی">
                    <span style="opacity:0.5;cursor:not-allowed;">بعدی</span>
                </li>
            @endif
        </ul>
    </nav>
@endif