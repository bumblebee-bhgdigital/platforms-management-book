{{-- Ein Book in der Sidebar: Link zur Detailseite + Hover-Aktionen Verknüpfen/Umhängen/Lösen + Knoten-Picker --}}
@props(['book', 'linked' => false, 'linkingBookId' => null, 'linkCandidates' => []])

@php
    $dot = match ($book->status->value) {
        'active' => 'bg-[var(--nx-success)]',
        'superseded' => 'bg-[var(--nx-warning)]',
        default => 'bg-[var(--nx-muted)] opacity-40',
    };
    $isLinking = $linkingBookId === $book->id;
@endphp

<div wire:key="mb-row-{{ $book->id }}" class="group/row flex flex-col">
    <div class="flex items-center gap-1.5 py-0.5 pl-3 pr-1 text-[var(--nx-text)] hover:text-[var(--nx-accent)] transition">
        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $dot }}" title="{{ $book->status->label() }}"></span>
        <a href="{{ route('management-book.books.show', ['managementBook' => $book]) }}" wire:navigate
           title="{{ $book->title }} ({{ $book->status->label() }})"
           class="truncate text-[11px] flex-1 {{ $book->status->value === 'superseded' ? 'opacity-60' : '' }}">{{ $book->title }}</a>

        @can('update', $book)
            <span class="flex items-center gap-0.5 opacity-0 group-hover/row:opacity-100 focus-within:opacity-100 transition-opacity">
                <button type="button" wire:click="startLinking({{ $book->id }})" title="{{ $linked ? 'An anderen Knoten umhängen' : 'Mit Knoten verknüpfen' }}"
                        class="p-0.5 rounded text-[var(--nx-muted)] hover:text-[var(--nx-text)]">
                    @svg($linked ? 'heroicon-o-arrows-right-left' : 'heroicon-o-link', 'w-3 h-3')
                </button>
                @if($linked)
                    <button type="button" wire:click="unlinkBook({{ $book->id }})" wire:confirm="Verknüpfung mit dem Knoten lösen?" title="Verknüpfung lösen"
                            class="p-0.5 rounded text-[var(--nx-muted)] hover:text-[var(--nx-danger)]">
                        @svg('heroicon-o-link-slash', 'w-3 h-3')
                    </button>
                @endif
            </span>
        @endcan
    </div>

    @if($isLinking)
        <div class="mx-2 mb-1 mt-0.5 rounded-md border border-[var(--nx-line)] bg-[var(--nx-surface)] p-1.5">
            <div class="flex items-center gap-1 mb-1">
                <input type="text" wire:model.live.debounce.300ms="linkSearch" placeholder="Knoten suchen …" autofocus
                       class="w-full rounded border border-[var(--nx-line)] bg-transparent px-1.5 py-0.5 text-[11px] text-[var(--nx-text)]" />
                <button type="button" wire:click="cancelLinking" title="Abbrechen" class="p-0.5 text-[var(--nx-muted)] hover:text-[var(--nx-text)]">
                    @svg('heroicon-o-x-mark', 'w-3 h-3')
                </button>
            </div>
            @forelse($linkCandidates as $candidate)
                <button type="button" wire:key="mb-cand-{{ $book->id }}-{{ $candidate['id'] }}" wire:click="linkTo({{ $candidate['id'] }})"
                        class="flex w-full items-center gap-1 rounded px-1 py-0.5 text-left text-[11px] text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                    <span class="truncate">{{ $candidate['name'] }}</span>
                    @if($candidate['type'])<span class="ml-auto text-[9px] uppercase tracking-wider text-[var(--nx-muted)]">{{ $candidate['type'] }}</span>@endif
                </button>
            @empty
                <div class="px-1 py-0.5 text-[11px] text-[var(--nx-muted)]">Keine passenden Knoten.</div>
            @endforelse
        </div>
    @endif
</div>
