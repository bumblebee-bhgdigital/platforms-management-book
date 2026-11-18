{{--
    Übersicht aller Management Books (nx-Design-System).
    Shell: x-ui-page*; Inhalt: x-nx-* + var(--nx-*). Datenform "gleichförmige Datensätze" -> Tabelle.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Management Books" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Management Books', 'icon' => 'book-open'],
        ]">
            @can('create', \Platform\ManagementBook\Models\ManagementBook::class)
                <x-nx-button variant="primary" size="sm" wire:click="openCreate">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neu</span>
                </x-nx-button>
            @endcan
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-stat-grid :cols="4">
            <x-nx-stat label="Books gesamt" :value="$stats['total']" icon="heroicon-o-book-open" />
            <x-nx-stat label="Aktiv" :value="$stats['active']" icon="heroicon-o-check-circle" accent="var(--nx-success)" />
            <x-nx-stat label="Entwurf" :value="$stats['draft']" icon="heroicon-o-pencil-square" />
            <x-nx-stat label="Review fällig" :value="$stats['review_due']" icon="heroicon-o-clock" accent="var(--nx-warning)" />
        </x-nx-stat-grid>

        <x-nx-section icon="heroicon-o-list-bullet" title="Alle Books" :hint="$books->count()"
                      description="Pro Knoten ist genau ein Book aktiv; weitere Books (Entwurf, abgelöst, archiviert) bleiben als Planung bzw. Historie erhalten.">
            <x-slot name="action">
                <x-nx-input-select name="statusFilter" size="sm" :options="$statusOptions" nullable="true" nullLabel="Alle Status" wire:model.live="statusFilter" />
            </x-slot>

            @if($books->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-book-open">
                        Noch keine Management Books{{ $statusFilter !== '' ? ' in diesem Status' : '' }}.
                        @can('create', \Platform\ManagementBook\Models\ManagementBook::class)
                            <x-slot name="action">
                                <x-nx-button variant="secondary" size="sm" wire:click="openCreate">Erstes Book anlegen</x-nx-button>
                            </x-slot>
                        @endcan
                    </x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush>
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Titel</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Knoten</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Status</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Horizont</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Gültig</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Owner</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right">Nächstes Review</x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach($books as $book)
                                <x-nx-table-row wire:key="book-row-{{ $book->id }}" :href="route('management-book.books.show', $book)">
                                    <x-nx-table-cell>
                                        {{ $book->title }}
                                        @if($multiTeam && $book->team)
                                            <span class="ml-1 text-xs text-[color:var(--nx-muted)]">{{ $book->team->name }}</span>
                                        @endif
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>
                                        @forelse($nodesByBook[$book->id] ?? [] as $node)
                                            <span class="text-xs">{{ $node['name'] }}</span>@if(! $loop->last), @endif
                                        @empty
                                            <span class="text-xs text-[color:var(--nx-muted)]">Unverknüpft</span>
                                        @endforelse
                                    </x-nx-table-cell>
                                    <x-nx-table-cell><x-nx-badge :variant="$book->status->badgeVariant()" dot>{{ $book->status->label() }}</x-nx-badge></x-nx-table-cell>
                                    <x-nx-table-cell>{{ $book->horizon_year ?? '–' }}</x-nx-table-cell>
                                    <x-nx-table-cell>
                                        {{ $book->valid_from?->format('d.m.Y') ?? '–' }} – {{ $book->valid_until?->format('d.m.Y') ?? '–' }}
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>{{ $book->owner?->name ?? '–' }}</x-nx-table-cell>
                                    <x-nx-table-cell align="right">{{ $book->next_review_at?->format('d.m.Y') ?? '–' }}</x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                </x-nx-card>
            @endif
        </x-nx-section>
    </x-ui-page-container>

    {{-- Die Modul-Sidebar (Books nach Knoten) kommt aus management-book.sidebar (Shell), wie im Planner. --}}

    {{-- Anlegen --}}
    <x-nx-modal wire:model="showCreate" size="md">
        <x-slot name="header">Management Book anlegen</x-slot>
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
            <p class="text-xs text-[color:var(--nx-muted)]">Das Book startet als Entwurf. Knoten-Verknüpfung und Aktivierung folgen auf der Detailseite bzw. in der Sidebar.</p>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showCreate', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="create">Anlegen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
