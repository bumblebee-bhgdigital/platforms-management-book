{{--
    Detailseite Management Book (nx-Design-System): Eigenschaften + Platzhalter-Bereiche.
    Strategie / Risiken / Analysen / Gremien folgen in eigenen Boards und docken hier als Bereiche an.
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

        {{-- Kopf --}}
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="m-0 text-xl font-semibold text-[color:var(--nx-text)] truncate">{{ $book->title }}</h1>
                @if($book->description)
                    <p class="mt-1 mb-0 text-sm text-[color:var(--nx-muted)] whitespace-pre-line">{{ $book->description }}</p>
                @endif
            </div>
            <x-nx-badge :variant="$book->status->badgeVariant()" dot>{{ $book->status->label() }}</x-nx-badge>
        </div>

        {{-- Eigenschaften --}}
        <x-nx-card>
            <x-nx-property-row icon="heroicon-o-building-office" label="Knoten">
                <div class="flex flex-wrap items-center gap-2">
                    @forelse($nodes as $node)
                        <a href="{{ route('organization.entities.show', $node) }}" wire:navigate class="inline-flex items-center gap-1 text-sm hover:underline">
                            {{ $node->name }}
                            @if($node->type)<span class="text-xs text-[color:var(--nx-muted)]">({{ $node->type->name }})</span>@endif
                        </a>
                    @empty
                        <span class="text-[color:var(--nx-muted)]">Nicht verknüpft</span>
                    @endforelse

                    @can('update', $book)
                        <button type="button" wire:click="startLinking({{ $book->id }})" class="text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] underline">
                            {{ $nodes->isEmpty() ? 'Mit Knoten verknüpfen' : 'Umhängen' }}
                        </button>
                        @if($nodes->isNotEmpty())
                            <button type="button" wire:click="unlinkBook({{ $book->id }})" wire:confirm="Verknüpfung mit dem Knoten lösen?" class="text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)] underline">Lösen</button>
                        @endif
                    @endcan
                </div>

                @if($linkingBookId === $book->id)
                    <div class="mt-2 rounded-md border border-[color:var(--nx-line)] p-2">
                        <div class="flex items-center gap-2 mb-1">
                            <input type="text" wire:model.live.debounce.300ms="linkSearch" placeholder="Knoten suchen …"
                                   class="w-full rounded border border-[color:var(--nx-line)] bg-transparent px-2 py-1 text-sm text-[color:var(--nx-text)]" />
                            <x-nx-button variant="ghost" size="sm" wire:click="cancelLinking">Abbrechen</x-nx-button>
                        </div>
                        @forelse($linkCandidates as $candidate)
                            <button type="button" wire:key="cand-{{ $candidate['id'] }}" wire:click="linkTo({{ $candidate['id'] }})"
                                    class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-[color:var(--nx-hover)]">
                                <span class="truncate">{{ $candidate['name'] }}</span>
                                @if($candidate['type'])<span class="ml-auto text-xs text-[color:var(--nx-muted)]">{{ $candidate['type'] }}</span>@endif
                            </button>
                        @empty
                            <div class="px-2 py-1 text-sm text-[color:var(--nx-muted)]">Keine passenden Knoten (Schreibrecht erforderlich).</div>
                        @endforelse
                    </div>
                @endif
            </x-nx-property-row>
            <x-nx-property-row icon="heroicon-o-calendar" label="Zieljahr / Horizont">{{ $book->horizon_year ?? '–' }}</x-nx-property-row>
            <x-nx-property-row icon="heroicon-o-calendar-days" label="Gültig">
                {{ $book->valid_from?->format('d.m.Y') ?? '–' }} – {{ $book->valid_until?->format('d.m.Y') ?? '–' }}
                <span class="ml-1 text-xs text-[color:var(--nx-muted)]">(informativ, Überschneidungen erlaubt)</span>
            </x-nx-property-row>
            <x-nx-property-row icon="heroicon-o-user" label="Owner">{{ $book->owner?->name ?? '–' }}</x-nx-property-row>
            <x-nx-property-row icon="heroicon-o-cpu-chip" label="Verantwortlicher Agent">{{ $book->responsibleAgent?->name ?? '–' }}</x-nx-property-row>
            <x-nx-property-row icon="heroicon-o-arrow-path" label="Review">
                {{ $book->review_rhythm?->label() ?? '–' }}
                @if($book->next_review_at) · nächstes {{ $book->next_review_at->format('d.m.Y') }} @endif
            </x-nx-property-row>
        </x-nx-card>

        {{-- Platzhalter-Bereiche (folgen in eigenen Boards) --}}
        <x-nx-section icon="heroicon-o-flag" title="Strategie" description="Strategische Ziele, Leitplanken und Maßnahmen dieses Knotens.">
            <x-nx-card><x-nx-empty icon="heroicon-o-flag">Noch keine Strategie — dieser Bereich folgt in einem eigenen Board.</x-nx-empty></x-nx-card>
        </x-nx-section>

        <x-nx-section icon="heroicon-o-exclamation-triangle" title="Risiken" description="Risikoregister und Bewertung.">
            <x-nx-card><x-nx-empty icon="heroicon-o-exclamation-triangle">Noch keine Risiken — dieser Bereich folgt in einem eigenen Board.</x-nx-empty></x-nx-card>
        </x-nx-section>

        <x-nx-section icon="heroicon-o-chart-bar" title="Analysen" description="Lage-, Umfeld- und Wirkungsanalysen.">
            <x-nx-card><x-nx-empty icon="heroicon-o-chart-bar">Noch keine Analysen — dieser Bereich folgt in einem eigenen Board.</x-nx-empty></x-nx-card>
        </x-nx-section>

        <x-nx-section icon="heroicon-o-user-group" title="Gremien" :hint="$committees->count()" description="Gremien (z. B. Lenkungsausschuss) mit Sitzungen, Agenda, Unterlagen, Protokoll und Beschlüssen.">
            <x-nx-card><x-nx-empty icon="heroicon-o-user-group">Noch keine Gremien — Gremien und Sitzungen folgen in eigenen Boards (Kaskade: Book → Gremium → Sitzung).</x-nx-empty></x-nx-card>
        </x-nx-section>
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
