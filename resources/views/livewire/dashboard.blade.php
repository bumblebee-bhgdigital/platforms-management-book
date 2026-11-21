{{--
    Cockpit über alle sichtbaren Management Books (nx-Design-System): Kennzahlen, nächste Sitzungen, offene Beschlüsse,
    Top-Risiken (BookInsights::forBooks), darunter alle Books als Tabelle (gleichförmige Datensätze).
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
        {{-- Kennzahlen über alle sichtbaren Books --}}
        <x-nx-stat-grid :cols="5">
            <x-nx-stat label="Aktive Books" :value="$i['active_books']" :hint="$stats['total'] . ' gesamt · ' . $stats['draft'] . ' Entwurf'" icon="heroicon-o-book-open" accent="var(--nx-success)" />
            <x-nx-stat label="Kritische / hohe Risiken" :value="$i['risks_critical'] . ' / ' . $i['risks_high']" hint="offene Risiken mit Score ≥ 10"
                       icon="heroicon-o-exclamation-triangle" :accent="$i['risks_critical'] > 0 ? 'var(--nx-danger)' : null" />
            <x-nx-stat label="Sitzungen (30 Tage)" :value="$i['upcoming_meetings']->count()" hint="anstehend" icon="heroicon-o-calendar" />
            <x-nx-stat label="Offene Beschlüsse" :value="$i['open_decisions']" :hint="$i['overdue_decisions'] ? $i['overdue_decisions'] . ' überfällig' : 'keine überfällig'"
                       icon="heroicon-o-check-badge" :accent="$i['overdue_decisions'] ? 'var(--nx-warning)' : null" />
            <x-nx-stat label="Meilensteine überfällig" :value="$i['overdue_milestones']->count()" :hint="$stats['review_due'] ? $stats['review_due'] . ' Review(s) fällig' : 'Reviews im Plan'"
                       icon="heroicon-o-flag" :accent="$i['overdue_milestones']->isNotEmpty() ? 'var(--nx-warning)' : null" />
        </x-nx-stat-grid>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Nächste Sitzungen --}}
            <x-nx-section icon="heroicon-o-calendar" title="Nächste Sitzungen" :hint="$i['upcoming_meetings']->count()">
                @if($i['upcoming_meetings']->isEmpty())
                    <x-nx-card><x-nx-empty icon="heroicon-o-calendar">Keine Sitzungen in den nächsten 30 Tagen.</x-nx-empty></x-nx-card>
                @else
                    <x-nx-card flush>
                        <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                            @foreach($i['upcoming_meetings']->take(6) as $m)
                                <li wire:key="dash-meeting-{{ $m->id }}">
                                    <x-nx-list-item icon="heroicon-o-calendar-days" :title="$m->title"
                                                    :subtitle="($m->committee?->name ?? 'Gremium') . ' · ' . ($i['book_titles'][$m->committee?->management_book_id] ?? '')"
                                                    :meta="$m->scheduled_at->format('d.m. H:i')"
                                                    :href="route('management-book.meetings.show', $m)" />
                                </li>
                            @endforeach
                        </ul>
                    </x-nx-card>
                @endif
            </x-nx-section>

            {{-- Offene Beschlüsse --}}
            <x-nx-section icon="heroicon-o-check-badge" title="Offene Beschlüsse" :hint="$i['open_decisions']">
                @if($i['decisions']->isEmpty())
                    <x-nx-card><x-nx-empty icon="heroicon-o-check-badge">Keine offenen Beschlüsse.</x-nx-empty></x-nx-card>
                @else
                    <x-nx-card flush>
                        <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                            @foreach($i['decisions'] as $d)
                                @php
                                    $overdue = $d->due_date && $d->due_date->lt(today());
                                    $meeting = $i['meeting_by_id'][$d->meeting_id] ?? null;
                                @endphp
                                <li wire:key="dash-dec-{{ $d->id }}">
                                    <x-nx-list-item icon="heroicon-o-check-badge" :title="$d->text"
                                                    :subtitle="($d->ownerUser?->name ?? $d->ownerAgent?->name ?? 'ohne Verantwortliche:n') . ($meeting ? ' · ' . $meeting->title : '')"
                                                    :href="route('management-book.meetings.show', $d->meeting_id)">
                                        <x-slot name="trailing">
                                            @if($d->due_date)
                                                <x-nx-badge :variant="$overdue ? 'warning' : 'neutral'">{{ $overdue ? 'überfällig ' : 'bis ' }}{{ $d->due_date->format('d.m.') }}</x-nx-badge>
                                            @endif
                                        </x-slot>
                                    </x-nx-list-item>
                                </li>
                            @endforeach
                        </ul>
                    </x-nx-card>
                @endif
            </x-nx-section>

            {{-- Top-Risiken --}}
            <x-nx-section icon="heroicon-o-exclamation-triangle" title="Top-Risiken" hint="nach Score">
                @if($i['top_risks']->isEmpty())
                    <x-nx-card><x-nx-empty icon="heroicon-o-shield-check">Keine bewerteten offenen Risiken.</x-nx-empty></x-nx-card>
                @else
                    <x-nx-card flush>
                        <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                            @foreach($i['top_risks'] as $r)
                                @php $level = $r->level(); @endphp
                                <li wire:key="dash-risk-{{ $r->id }}">
                                    <x-nx-list-item :title="$r->title" :subtitle="$i['book_titles'][$r->management_book_id] ?? ''"
                                                    :href="route('management-book.books.show', $r->management_book_id) . '?tab=risks'">
                                        <x-slot name="leading">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-[6px] text-xs font-semibold tabular-nums text-[color:var(--nx-text)]"
                                                  style="background: {{ $level?->tint() }};" title="{{ $level?->label() }}">{{ $r->score }}</span>
                                        </x-slot>
                                        <x-slot name="trailing">
                                            <span class="text-xs text-[color:var(--nx-faint)]">E{{ $r->probability }}·A{{ $r->impact }}</span>
                                        </x-slot>
                                    </x-nx-list-item>
                                </li>
                            @endforeach
                        </ul>
                    </x-nx-card>
                @endif
            </x-nx-section>
        </div>

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
                    <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                        @foreach($books as $book)
                            @php $pb = $i['per_book'][$book->id] ?? []; @endphp
                            <li wire:key="book-row-{{ $book->id }}">
                                <a href="{{ route('management-book.books.show', $book) }}" wire:navigate
                                   class="group flex items-center gap-4 px-4 py-3 transition-colors hover:bg-[color:var(--nx-hover)]">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)]">
                                        @svg('heroicon-o-book-open', 'w-4 h-4 text-[color:var(--nx-muted)]')
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-2">
                                            <span class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $book->title }}</span>
                                            <x-nx-badge :variant="$book->status->badgeVariant()" dot>{{ $book->status->label() }}</x-nx-badge>
                                        </span>
                                        <span class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-[color:var(--nx-faint)]">
                                            <span class="inline-flex items-center gap-1">
                                                @svg('heroicon-o-building-office', 'w-3.5 h-3.5')
                                                @forelse($nodesByBook[$book->id] ?? [] as $node){{ $node['name'] }}@if(! $loop->last), @endif
                                                @empty Unverknüpft
                                                @endforelse
                                            </span>
                                            @if($book->horizon_year)<span>Horizont {{ $book->horizon_year }}</span>@endif
                                            @if($book->owner)<span>{{ $book->owner->name }}</span>@endif
                                            @if($multiTeam && $book->team)<span>{{ $book->team->name }}</span>@endif
                                        </span>
                                    </span>
                                    {{-- Lage des Books: nur, was Aufmerksamkeit braucht, sonst ruhig --}}
                                    <span class="hidden shrink-0 items-center gap-4 text-xs text-[color:var(--nx-muted)] md:flex">
                                        <span class="w-20 text-right tabular-nums" title="Geschriebene Kapitel">{{ $pb['chapters'] ?? 0 }}/9 Kapitel</span>
                                        <span class="w-24 text-right tabular-nums {{ ($pb['risks_critical'] ?? 0) > 0 ? 'text-[color:var(--nx-danger)]' : (($pb['risks_hot'] ?? 0) > 0 ? 'text-[color:var(--nx-warning)]' : '') }}" title="Kritische und hohe Risiken">
                                            {{ $pb['risks_hot'] ?? 0 }} Risiken ≥ hoch
                                        </span>
                                        <span class="w-28 text-right tabular-nums {{ ($pb['overdue_decisions'] ?? 0) > 0 ? 'text-[color:var(--nx-warning)]' : '' }}" title="Offene Beschlüsse">
                                            {{ $pb['open_decisions'] ?? 0 }} Beschlüsse{{ ($pb['overdue_decisions'] ?? 0) > 0 ? ' (' . $pb['overdue_decisions'] . ' überf.)' : '' }}
                                        </span>
                                        <span class="w-28 text-right tabular-nums" title="Nächste Sitzung">
                                            {{ ($pb['next_meeting'] ?? null)?->scheduled_at?->format('d.m. H:i') ?? 'keine Sitzung' }}
                                        </span>
                                    </span>
                                    @svg('heroicon-o-chevron-right', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)] transition-transform group-hover:translate-x-0.5')
                                </a>
                            </li>
                        @endforeach
                    </ul>
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
