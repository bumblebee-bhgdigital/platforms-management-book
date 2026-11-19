{{-- Rekursiver Knoten (Entity) mit seinen Books für den Sidebar-Baum --}}
@props(['node', 'typeIcon' => null, 'depth' => 0, 'linkingBookId' => null, 'linkCandidates' => []])

<div wire:key="mb-entity-{{ $node['entity_id'] }}"
     x-data="{ open: localStorage.getItem('management-book.entity.' + {{ $node['entity_id'] }}) !== 'false' }"
     class="flex flex-col">
    <button type="button"
            @click="open = !open; localStorage.setItem('management-book.entity.' + {{ $node['entity_id'] }}, open)"
            class="flex items-center gap-1 py-1 px-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition w-full text-left">
        <span class="w-3 h-3 flex-shrink-0 flex items-center justify-center transition-transform text-[var(--nx-muted)]" :class="open ? 'rotate-90' : ''">
            @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
        </span>
        <span class="truncate text-xs font-medium">{{ $node['entity_name'] }}</span>
        <span class="ml-auto text-[10px] tabular-nums text-[var(--nx-muted)] opacity-60">{{ $node['total_books'] }}</span>
    </button>

    <div x-show="open" x-collapse class="flex flex-col ml-3 border-l border-[var(--nx-line)]">
        @foreach($node['books'] as $book)
            @include('management-book::livewire.partials.sidebar-book-row', ['book' => $book, 'linked' => true, 'linkingBookId' => $linkingBookId, 'linkCandidates' => $linkCandidates])
        @endforeach

        @foreach($node['children_by_type'] as $typeGroup)
            <div wire:key="mb-entity-{{ $node['entity_id'] }}-type-{{ $typeGroup['type_id'] }}" class="flex flex-col">
                @if($node['children_by_type']->count() > 1 || $node['books']->isNotEmpty())
                    <div class="mt-1 mb-0.5 pl-2.5 pr-2 text-[9px] uppercase tracking-wider text-[var(--nx-muted)] opacity-60">{{ $typeGroup['type_name'] }}</div>
                @endif
                @foreach($typeGroup['children'] as $child)
                    @include('management-book::livewire.partials.sidebar-entity-node', [
                        'node' => $child,
                        'typeIcon' => $typeGroup['type_icon'] ?? $typeIcon,
                        'depth' => $depth + 1,
                        'linkingBookId' => $linkingBookId,
                        'linkCandidates' => $linkCandidates,
                    ])
                @endforeach
            </div>
        @endforeach
    </div>
</div>
