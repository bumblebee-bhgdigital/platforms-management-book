{{--
    Sidebar View (nx-Design-System) — Management Books nach Knoten gruppiert, Unverknüpfte separat.
    Struktur wie planner::livewire.sidebar. Nur var(--nx-*) Tokens.
--}}

<div>
    <a x-show="!collapsed" href="{{ route('management-book.dashboard') }}" wire:navigate
       class="block p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[var(--nx-line)] mb-2 hover:text-[var(--nx-accent)]">
        Management Books
    </a>

    {{-- Eingeklappt: Einstieg in die Übersicht --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--nx-line)]">
        <a href="{{ route('management-book.dashboard') }}" wire:navigate title="Management Books"
           class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('management-book.*') ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
            @svg('heroicon-o-book-open', 'w-5 h-5')
        </a>
    </div>

    <div class="mt-2" x-show="!collapsed">
        {{-- Knoten-Gruppen (Entity-Typ -> Baum -> Books) --}}
        @foreach($entityTypeGroups as $typeGroup)
            <x-ui-sidebar-list wire:key="mb-type-group-{{ $typeGroup['type_id'] }}" :label="$typeGroup['type_name']">
                @foreach($typeGroup['entities'] as $entityNode)
                    @include('management-book::livewire.partials.sidebar-entity-node', [
                        'node' => $entityNode,
                        'typeIcon' => $typeGroup['type_icon'] ?? null,
                        'linkingBookId' => $linkingBookId,
                        'linkCandidates' => $linkCandidates,
                    ])
                @endforeach
            </x-ui-sidebar-list>
        @endforeach

        {{-- Nicht verknüpfte Books --}}
        @if($unlinkedBooks->isNotEmpty())
            <x-ui-sidebar-list label="Unverknüpft">
                @foreach($unlinkedBooks as $book)
                    @include('management-book::livewire.partials.sidebar-book-row', ['book' => $book, 'linked' => false, 'linkingBookId' => $linkingBookId, 'linkCandidates' => $linkCandidates])
                @endforeach
            </x-ui-sidebar-list>
        @endif

        @if($entityTypeGroups->isEmpty() && $unlinkedBooks->isEmpty())
            <div class="px-3 py-1 text-xs text-[var(--nx-muted)]">Keine Management Books</div>
        @endif

        {{-- Showcase (Gerüst-Beispiel) bleibt erreichbar --}}
        <x-ui-sidebar-list label="Entwicklung">
            <x-ui-sidebar-item :href="route('management-book.showcase')">
                @svg('heroicon-o-swatch', 'w-4 h-4 text-[var(--nx-text)]')
                <span class="ml-2 text-sm">nx-Showcase</span>
            </x-ui-sidebar-item>
        </x-ui-sidebar-list>
    </div>
</div>
