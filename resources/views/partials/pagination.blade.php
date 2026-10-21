@if ($paginator->hasPages())
    <nav class="pagination" aria-label="ページ送り">
        @if ($paginator->onFirstPage())<span>前へ</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">前へ</a>@endif
        <span>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">次へ</a>@else<span>次へ</span>@endif
    </nav>
@endif
