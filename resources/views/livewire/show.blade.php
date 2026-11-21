{{--
    Detailseite Management Book (nx-Design-System): Kopf mit Eckdaten und Knoten, darunter Bereiche als Tabs.
    Jeder Bereich ist eine eigene Livewire-Komponente unter Livewire/Book (management-book.book.*).
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$book->title" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Management Books', 'href' => route('management-book.dashboard'), 'icon' => 'book-open'],
            ['label' => $book->title],
        ]">
            @can('update', $book)
                {{-- ≥2 Aktionen -> ein Dropdown --}}
                <x-nx-dropdown label="Aktionen">
                    <x-nx-dropdown-item wire:click="openEdit">@svg('heroicon-o-pencil-square', 'w-4 h-4') Bearbeiten</x-nx-dropdown-item>
                    @if($book->status !== \Platform\ManagementBook\Enums\ManagementBookStatus::ACTIVE && $book->status !== \Platform\ManagementBook\Enums\ManagementBookStatus::ARCHIVED)
                        <x-nx-dropdown-item wire:click="activate">@svg('heroicon-o-check-circle', 'w-4 h-4') Aktivieren</x-nx-dropdown-item>
                    @endif
                    @if($book->status !== \Platform\ManagementBook\Enums\ManagementBookStatus::ARCHIVED)
                        <x-nx-dropdown-item wire:click="archive" wire:confirm="Book archivieren?">@svg('heroicon-o-archive-box', 'w-4 h-4') Archivieren</x-nx-dropdown-item>
                    @endif
                    @can('delete', $book)
                        <x-nx-dropdown-divider />
                        <x-nx-dropdown-item variant="danger" wire:click="deleteBook" wire:confirm="Book löschen? (Soft-Delete)">@svg('heroicon-o-trash', 'w-4 h-4') Löschen</x-nx-dropdown-item>
                    @endcan
                </x-nx-dropdown>
            @endcan
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        @if(session('error'))
            <x-nx-callout variant="danger" icon="heroicon-o-exclamation-triangle" title="Fehler">{{ session('error') }}</x-nx-callout>
        @endif

        @if($wouldSupersede->isNotEmpty())
            <x-nx-callout variant="info" icon="heroicon-o-information-circle" title="Aktivieren löst ab">
                Am selben Knoten ist bereits aktiv:
                {{ $wouldSupersede->map(fn ($b) => '„' . $b->title . '“')->implode(', ') }}.
                Beim Aktivieren wird es automatisch auf „abgelöst“ gesetzt und bleibt als Historie erhalten.
            </x-nx-callout>
        @endif

        {{-- Kopf: Titel, Status, Knoten und Eckdaten --}}
        <div class="space-y-3">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="m-0 truncate text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $book->title }}</h1>
                        <x-nx-badge :variant="$book->status->badgeVariant()" dot>{{ $book->status->label() }}</x-nx-badge>
                    </div>
                    @if($book->description)
                        <p class="mt-1 mb-0 max-w-3xl text-sm text-[color:var(--nx-muted)] whitespace-pre-line">{{ $book->description }}</p>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-[color:var(--nx-muted)]">
                <span class="inline-flex items-center gap-1.5">
                    @svg('heroicon-o-building-office', 'w-4 h-4 text-[color:var(--nx-faint)]')
                    @forelse($nodes as $node)
                        <a href="{{ route('organization.entities.show', $node) }}" wire:navigate class="text-[color:var(--nx-text)] hover:underline">{{ $node->name }}</a>@if(! $loop->last),@endif
                    @empty
                        <span>Nicht verknüpft</span>
                    @endforelse
                    @can('update', $book)
                        <button type="button" wire:click="startLinking({{ $book->id }})" class="ml-1 text-xs underline hover:text-[color:var(--nx-text)]">
                            {{ $nodes->isEmpty() ? 'verknüpfen' : 'umhängen' }}
                        </button>
                        @if($nodes->isNotEmpty())
                            <button type="button" wire:click="unlinkBook({{ $book->id }})" wire:confirm="Verknüpfung mit dem Knoten lösen?" class="text-xs underline hover:text-[color:var(--nx-danger)]">lösen</button>
                        @endif
                    @endcan
                </span>
                @if($book->horizon_year)
                    <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-flag', 'w-4 h-4 text-[color:var(--nx-faint)]') Horizont {{ $book->horizon_year }}</span>
                @endif
                @if($book->valid_from || $book->valid_until)
                    <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-calendar-days', 'w-4 h-4 text-[color:var(--nx-faint)]')
                        {{ $book->valid_from ? 'ab ' . $book->valid_from->format('d.m.Y') : '' }}{{ $book->valid_from && $book->valid_until ? ' ' : '' }}{{ $book->valid_until ? 'bis ' . $book->valid_until->format('d.m.Y') : '' }}
                    </span>
                @endif
                @if($book->owner)
                    <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-user', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $book->owner->name }}</span>
                @endif
                @if($book->responsibleAgent)
                    <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-cpu-chip', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $book->responsibleAgent->name }}</span>
                @endif
                @if($book->review_rhythm || $book->next_review_at)
                    <span class="inline-flex items-center gap-1.5 {{ $book->next_review_at && $book->next_review_at->isPast() ? 'text-[color:var(--nx-warning)]' : '' }}">
                        @svg('heroicon-o-arrow-path', 'w-4 h-4 text-[color:var(--nx-faint)]')
                        Review{{ $book->review_rhythm ? ' ' . $book->review_rhythm->label() : '' }}{{ $book->next_review_at ? ' · nächstes ' . $book->next_review_at->format('d.m.Y') : '' }}
                    </span>
                @endif
                @can('update', $book)
                    @if(! $book->horizon_year || ! $book->review_rhythm || ! $book->next_review_at)
                        <button type="button" wire:click="openEdit" class="inline-flex items-center gap-1 text-xs text-[color:var(--nx-muted)] underline hover:text-[color:var(--nx-text)]">
                            @svg('heroicon-o-plus-small', 'w-3.5 h-3.5') Eckdaten ergänzen (Horizont, Review-Rhythmus)
                        </button>
                    @endif
                @endcan
            </div>

            @if($linkingBookId === $book->id)
                <div class="max-w-md rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-2">
                    <div class="mb-1 flex items-center gap-2">
                        <input type="text" wire:model.live.debounce.300ms="linkSearch" placeholder="Knoten suchen …"
                               class="w-full rounded-[6px] border border-[color:var(--nx-line)] bg-transparent px-2 py-1 text-sm text-[color:var(--nx-text)]" />
                        <x-nx-button variant="ghost" size="sm" wire:click="cancelLinking">Abbrechen</x-nx-button>
                    </div>
                    @forelse($linkCandidates as $candidate)
                        <button type="button" wire:key="cand-{{ $candidate['id'] }}" wire:click="linkTo({{ $candidate['id'] }})"
                                class="flex w-full items-center gap-2 rounded-[6px] px-2 py-1 text-left text-sm hover:bg-[color:var(--nx-hover)]">
                            <span class="truncate">{{ $candidate['name'] }}</span>
                            @if($candidate['type'])<span class="ml-auto text-xs text-[color:var(--nx-muted)]">{{ $candidate['type'] }}</span>@endif
                        </button>
                    @empty
                        <div class="px-2 py-1 text-sm text-[color:var(--nx-muted)]">Keine passenden Knoten (Schreibrecht erforderlich).</div>
                    @endforelse
                </div>
            @endif
        </div>

        {{-- Bereiche --}}
        <div>
            <x-nx-tabs>
                @foreach($tabs as $key => $t)
                    <x-nx-tab :active="$activeTab === $key" wire:click="setTab('{{ $key }}')" wire:key="tab-{{ $key }}">
                        <span class="inline-flex items-center gap-1.5">@svg($t['icon'], 'w-4 h-4') {{ $t['label'] }}</span>
                    </x-nx-tab>
                @endforeach
            </x-nx-tabs>

            @switch($activeTab)
                @case('book')
                    <livewire:management-book.book.chapters :book-id="$book->id" :key="'book-chapters-' . $book->id" />
                    @break
                @case('strategy')
                    <livewire:management-book.book.strategy :book-id="$book->id" :key="'book-strategy-' . $book->id" />
                    @break
                @case('risks')
                    <livewire:management-book.book.risks :book-id="$book->id" :key="'book-risks-' . $book->id" />
                    @break
                @case('committees')
                    <livewire:management-book.book.committees :book-id="$book->id" :key="'book-committees-' . $book->id" />
                    @break
                @default
                    <livewire:management-book.book.overview :book-id="$book->id" :key="'book-overview-' . $book->id" />
            @endswitch
        </div>
    </x-ui-page-container>

    {{-- Bearbeiten --}}
    <x-nx-modal wire:model="showEdit" size="md">
        <x-slot name="header">Management Book bearbeiten</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="form.title" label="Titel" wire:model="form.title" required />
            <x-nx-input-textarea name="form.description" label="Beschreibung" wire:model="form.description" rows="3" />
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <x-nx-input-number name="form.horizon_year" label="Zieljahr" wire:model="form.horizon_year" />
                <x-nx-input-date name="form.valid_from" label="Gültig ab" wire:model="form.valid_from" />
                <x-nx-input-date name="form.valid_until" label="Gültig bis" wire:model="form.valid_until" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-nx-input-select name="form.review_rhythm" label="Review-Rhythmus" :options="$rhythmOptions" nullable="true" wire:model="form.review_rhythm" />
                <x-nx-input-date name="form.next_review_at" label="Nächstes Review" wire:model="form.next_review_at" />
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showEdit', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
