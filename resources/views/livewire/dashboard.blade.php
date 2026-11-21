{{--
    Cockpit über alle sichtbaren Management Books (nx-Design-System), breit und zweispaltig:
    links Einstieg, Kennzahlen, Books (Lage je Book), Sitzungen, Beschlüsse, Top-Risiken (BookInsights::forBooks);
    rechts Orientierung (Regelkreis in Kürze + Guide 'dashboard' mit Academy-Verweisen).
--}}
@php
    $guide = \Platform\ManagementBook\Support\Guide::for('dashboard');
    $canCreate = auth()->user()?->can('create', \Platform\ManagementBook\Models\ManagementBook::class);
    $cycle = [
        ['Strategiemeeting', 'Die Geschäftsführung klärt die Richtung.'],
        ['Workshop', 'Führungskräfte prüfen sie an der Wirklichkeit.'],
        ['Management Book', 'Die Orientierung wird verdichtet.'],
        ['Kick-off & Fokus', 'Wenige Themen, bewusst gewählt.'],
        ['Umsetzung', 'Die Einheiten handeln in eigener Verantwortung.'],
        ['Lenkungsausschuss', 'Was klemmt, wird reguliert.'],
        ['Rückkopplung', 'Was wir lernen, verändert das Book.'],
    ];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Management Books" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Management Books', 'icon' => 'book-open'],
        ]">
            @if($canCreate)
                <x-nx-button variant="primary" size="sm" wire:click="openCreate">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neues Book</span>
                </x-nx-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="full" spacing="space-y-8">
      <div class="mx-auto w-full max-w-[1440px]">
        <div class="grid gap-10 xl:grid-cols-[minmax(0,1fr)_300px]">
          <div class="min-w-0 space-y-10">

            {{-- Einstieg --}}
            <header class="space-y-3">
                <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">{{ $guide['eyebrow'] }}</div>
                <h1 class="m-0 text-3xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $guide['title'] }}</h1>
                <p class="m-0 max-w-3xl text-[15px] leading-relaxed text-[color:var(--nx-muted)]">{{ $guide['lead'] }}</p>
            </header>

            @if($stats['total'] === 0)
                {{-- Noch kein Book: erklären, wie eines entsteht, und den ersten Schritt anbieten --}}
                <section class="rounded-[12px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-8">
                    <div class="max-w-2xl space-y-4">
                        <h2 class="m-0 text-xl font-semibold text-[color:var(--nx-text)]">So entsteht ein Management Book</h2>
                        <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                            Das Book steht nicht am Anfang, es ist das Ergebnis der Strategiearbeit. Die Geschäftsführung klärt im Strategiemeeting die Richtung,
                            die Führungskräfte prüfen sie im Workshop an der Wirklichkeit. Das Book verdichtet beides zu einer gemeinsamen Sprache:
                            Herkunft, Standort, Mission, Vision, Prioritäten und Grundsätze.
                        </p>
                        <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                            Danach übersetzt der Kick-off das Book in wenige Fokusräume, die Einheiten setzen um, und der Lenkungsausschuss hält Strategie und
                            Realität im Gespräch. Was dort gelernt wird, fließt beim nächsten Review zurück ins Book.
                        </p>
                        <ol class="m-0 grid list-none gap-2 p-0 sm:grid-cols-2">
                            @foreach(['Book anlegen und mit der Einheit verknüpfen', 'Mission, Vision und Standort schreiben', '5–7 Fokusräume mit Meilensteinen festlegen', 'Lenkungsausschuss einrichten und die erste Sitzung planen'] as $n => $step)
                                <li class="flex items-start gap-3 rounded-[8px] border border-[color:var(--nx-line)] px-3 py-2.5 text-sm text-[color:var(--nx-text)]">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-[color:var(--nx-line-strong)] text-[11px] font-semibold tabular-nums text-[color:var(--nx-muted)]">{{ $n + 1 }}</span>
                                    {{ $step }}
                                </li>
                            @endforeach
                        </ol>
                        @if($canCreate)
                            <x-nx-button variant="primary" size="md" wire:click="openCreate">@svg('heroicon-o-plus', 'w-4 h-4') Erstes Book anlegen</x-nx-button>
                        @endif
                    </div>
                </section>
            @else
                {{-- Kennzahlen über alle sichtbaren Books --}}
                <x-nx-stat-grid :cols="5">
                    <x-nx-stat label="Aktive Books" :value="$i['active_books']" :hint="$stats['total'] . ' gesamt · ' . $stats['draft'] . ' Entwurf'" icon="heroicon-o-book-open" accent="var(--nx-success)" />
                    <x-nx-stat label="Kritische / hohe Risiken" :value="$i['risks_critical'] . ' / ' . $i['risks_high']" hint="offen, Score ≥ 10"
                               icon="heroicon-o-exclamation-triangle" :accent="$i['risks_critical'] > 0 ? 'var(--nx-danger)' : null" />
                    <x-nx-stat label="Sitzungen" :value="$i['upcoming_meetings']->count()" hint="in den nächsten 30 Tagen" icon="heroicon-o-calendar" />
                    <x-nx-stat label="Offene Beschlüsse" :value="$i['open_decisions']" :hint="$i['overdue_decisions'] ? $i['overdue_decisions'] . ' überfällig' : 'keine überfällig'"
                               icon="heroicon-o-check-badge" :accent="$i['overdue_decisions'] ? 'var(--nx-warning)' : null" />
                    <x-nx-stat label="Meilensteine überfällig" :value="$i['overdue_milestones']->count()" :hint="$stats['review_due'] ? $stats['review_due'] . ' Review(s) fällig' : 'Reviews im Plan'"
                               icon="heroicon-o-flag" :accent="$i['overdue_milestones']->isNotEmpty() ? 'var(--nx-warning)' : null" />
                </x-nx-stat-grid>

                {{-- Books: Lage je Book, Zeile = echter Link --}}
                <section class="space-y-3">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div class="space-y-1">
                            <h2 class="m-0 flex items-center gap-2 text-base font-semibold text-[color:var(--nx-text)]">
                                @svg('heroicon-o-book-open', 'w-4 h-4 text-[color:var(--nx-faint)]') Books
                                <span class="text-sm font-normal text-[color:var(--nx-faint)]">{{ $books->count() }}</span>
                            </h2>
                            <p class="m-0 max-w-2xl text-sm leading-relaxed text-[color:var(--nx-muted)]">
                                Pro Einheit ist genau ein Book aktiv. Entwürfe sind die Planung für die nächste Periode, abgelöste und archivierte Books bleiben als Historie erhalten.
                            </p>
                        </div>
                        <div class="w-44">
                            <x-nx-input-select name="statusFilter" size="sm" :options="$statusOptions" nullable="true" nullLabel="Alle Status" wire:model.live="statusFilter" />
                        </div>
                    </div>

                    @if($books->isEmpty())
                        <x-nx-card><x-nx-empty icon="heroicon-o-book-open">Keine Books in diesem Status.</x-nx-empty></x-nx-card>
                    @else
                        <x-nx-card flush>
                            <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                                @foreach($books as $book)
                                    @php $pb = $i['per_book'][$book->id] ?? []; @endphp
                                    <li wire:key="book-row-{{ $book->id }}">
                                        <a href="{{ route('management-book.books.show', $book) }}" wire:navigate
                                           class="group flex items-center gap-4 px-5 py-4 transition-colors hover:bg-[color:var(--nx-hover)]">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)]">
                                                @svg('heroicon-o-book-open', 'w-5 h-5 text-[color:var(--nx-muted)]')
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-center gap-2">
                                                    <span class="truncate text-[15px] font-medium text-[color:var(--nx-text)]">{{ $book->title }}</span>
                                                    <x-nx-badge :variant="$book->status->badgeVariant()" dot>{{ $book->status->label() }}</x-nx-badge>
                                                </span>
                                                <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-[color:var(--nx-muted)]">
                                                    <span class="inline-flex items-center gap-1">
                                                        @svg('heroicon-o-building-office', 'w-3.5 h-3.5 text-[color:var(--nx-faint)]')
                                                        @forelse($nodesByBook[$book->id] ?? [] as $node){{ $node['name'] }}@if(! $loop->last), @endif
                                                        @empty Keiner Einheit zugeordnet
                                                        @endforelse
                                                    </span>
                                                    @if($book->horizon_year)<span>Unterwegs nach {{ $book->horizon_year }}</span>@endif
                                                    @if($book->owner)<span>{{ $book->owner->name }}</span>@endif
                                                    @if($multiTeam && $book->team)<span>{{ $book->team->name }}</span>@endif
                                                </span>
                                            </span>
                                            {{-- Lage: nur, was Aufmerksamkeit braucht, ist farbig --}}
                                            <span class="hidden shrink-0 items-center gap-6 text-xs text-[color:var(--nx-muted)] lg:flex">
                                                <span class="w-20 text-right" title="Geschriebene Kapitel des Books">
                                                    <span class="block text-sm font-medium tabular-nums text-[color:var(--nx-text)]">{{ $pb['chapters'] ?? 0 }}/9</span>Kapitel
                                                </span>
                                                <span class="w-20 text-right" title="Offene Risiken mit Ampel hoch oder kritisch">
                                                    <span class="block text-sm font-medium tabular-nums {{ ($pb['risks_critical'] ?? 0) > 0 ? 'text-[color:var(--nx-danger)]' : (($pb['risks_hot'] ?? 0) > 0 ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-text)]') }}">{{ $pb['risks_hot'] ?? 0 }}</span>Risiken ≥ hoch
                                                </span>
                                                <span class="w-24 text-right" title="Offene Beschlüsse aus den Sitzungen">
                                                    <span class="block text-sm font-medium tabular-nums {{ ($pb['overdue_decisions'] ?? 0) > 0 ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-text)]' }}">{{ $pb['open_decisions'] ?? 0 }}{{ ($pb['overdue_decisions'] ?? 0) > 0 ? ' · ' . $pb['overdue_decisions'] . ' überf.' : '' }}</span>Beschlüsse
                                                </span>
                                                <span class="w-28 text-right" title="Nächste Sitzung eines Gremiums">
                                                    <span class="block text-sm font-medium tabular-nums text-[color:var(--nx-text)]">{{ ($pb['next_meeting'] ?? null)?->scheduled_at?->format('d.m. H:i') ?? '–' }}</span>nächste Sitzung
                                                </span>
                                            </span>
                                            @svg('heroicon-o-chevron-right', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)] transition-transform group-hover:translate-x-0.5')
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </x-nx-card>
                    @endif
                </section>

                {{-- Was gerade ansteht --}}
                <div class="grid gap-8 lg:grid-cols-2 2xl:grid-cols-3">
                    <x-nx-section icon="heroicon-o-calendar" title="Nächste Sitzungen" :hint="$i['upcoming_meetings']->count()"
                                  description="Wo Strategie und Wirklichkeit sich in den nächsten 30 Tagen begegnen.">
                        @if($i['upcoming_meetings']->isEmpty())
                            <x-nx-card><x-nx-empty icon="heroicon-o-calendar">Keine Sitzung in den nächsten 30 Tagen. Sitzungen plant ihr im Gremium eines Books.</x-nx-empty></x-nx-card>
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

                    <x-nx-section icon="heroicon-o-check-badge" title="Offene Beschlüsse" :hint="$i['open_decisions']"
                                  description="Was beschlossen, aber noch nicht umgesetzt ist. Überfällige zuerst.">
                        @if($i['decisions']->isEmpty())
                            <x-nx-card><x-nx-empty icon="heroicon-o-check-badge">Keine offenen Beschlüsse. Beschlüsse entstehen in den Sitzungen der Gremien.</x-nx-empty></x-nx-card>
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

                    <x-nx-section icon="heroicon-o-exclamation-triangle" title="Top-Risiken" hint="nach Score"
                                  description="Die höchsten offenen Risiken über alle Books, bewertet nach E × A.">
                        @if($i['top_risks']->isEmpty())
                            <x-nx-card><x-nx-empty icon="heroicon-o-shield-check">Noch keine bewerteten Risiken. Erfasst sie im Bereich „Risiken“ eines Books.</x-nx-empty></x-nx-card>
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
            @endif
          </div>

          {{-- Orientierung --}}
          <div class="space-y-6 self-start xl:sticky xl:top-6">
              <section class="space-y-3 rounded-[10px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-4">
                  <h3 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Der Regelkreis in Kürze</h3>
                  <ol class="m-0 list-none space-y-2 p-0">
                      @foreach($cycle as $n => [$name, $text])
                          <li class="flex items-start gap-2.5">
                              <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-[color:var(--nx-line-strong)] text-[10px] font-semibold tabular-nums text-[color:var(--nx-muted)]">{{ $n + 1 }}</span>
                              <span class="text-sm leading-snug"><span class="font-medium text-[color:var(--nx-text)]">{{ $name }}</span> <span class="text-[color:var(--nx-muted)]">– {{ $text }}</span></span>
                          </li>
                      @endforeach
                  </ol>
                  <p class="m-0 text-xs leading-relaxed text-[color:var(--nx-muted)]">Danach beginnt der Kreis von vorn, mit mehr Wissen als beim letzten Mal.</p>
              </section>
              @include('management-book::livewire.partials.guide-rail', ['area' => 'dashboard'])
          </div>
        </div>
      </div>
    </x-ui-page-container>

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
