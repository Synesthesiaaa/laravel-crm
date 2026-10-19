@if($paginator->hasPages())
<nav class="workspace-pagination" aria-label="Pagination">
    @if($paginator->onFirstPage())
        <span class="workspace-page is-disabled" aria-disabled="true">Previous</span>
    @else
        <a class="workspace-page" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
    @endif
    @foreach($elements as $element)
        @if(is_string($element))
            <span class="workspace-page-gap" aria-hidden="true">{{ $element }}</span>
        @else
            @foreach($element as $number => $url)
                @if($number === $paginator->currentPage())
                    <span class="workspace-page is-current" aria-current="page" aria-label="Page {{ $number }}">{{ $number }}</span>
                @else
                    <a class="workspace-page" href="{{ $url }}" aria-label="Go to page {{ $number }}">{{ $number }}</a>
                @endif
            @endforeach
        @endif
    @endforeach
    @if($paginator->hasMorePages())
        <a class="workspace-page" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
    @else
        <span class="workspace-page is-disabled" aria-disabled="true">Next</span>
    @endif
</nav>
@endif
