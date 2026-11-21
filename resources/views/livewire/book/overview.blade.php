{{--
    Book-Übersicht (Cockpit): Regelkreis, Kennzahlen, Orientierung, Risikoprofil, nächste Sitzung, was klemmt.
    Daten: Platform\ManagementBook\Services\BookInsights::forBook(). Navigation in andere Tabs über $parent.setTab().
--}}
@php
    $cycle = $i['cycle'];
    $current = $cycle[$station] ?? $cycle[0];
    $levels = collect(\Platform\ManagementBook\Enums\RiskLevel::cases());
    $plain = fn ($md, $len) => \Illuminate\Support\Str::limit(trim(strip_tags(\Illuminate\Support\Str::markdown((string) $md))), $len);
@endphp
<div class="space-y-8">

    {{-- Regelkreis --}}
    <x-nx-section icon="heroicon-o-arrow-path" title="Regelkreis"
                  description="Strategie wird nicht verkündet, sondern in der Organisation anschlussfähig gemacht. Wo steht dieses Book im Kreislauf?">
        <x-nx-card flush>
            <ol class="m-0 grid list-none grid-cols-2 gap-px p-0 sm:grid-cols-4 xl:grid-cols-8 bg-[color:var(--nx-line)]">
                @foreach($cycle as $idx => $s)
                    @php $st = $states[$s['state']]; @endphp
                    <li wire:key="station-{{ $s['key'] }}" class="bg-[color:var(--nx-surface)]">
                        <button type="button" wire:click="selectStation({{ $idx }})"
                                class="flex h-full w-full flex-col gap-2 px-3 py-3 text-left transition-colors hover:bg-[color:var(--nx-hover)] {{ $idx === $station ? 'bg-[color:var(--nx-hover)]' : '' }}">
                            <span class="flex items-center justify-between gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-semibold tabular-nums {{ $idx === $station ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)]' }}">{{ $idx + 1 }}</span>
                                @if($s['state'] === 'untracked')
                                    <span class="h-2 w-2 rounded-full border border-dashed border-[color:var(--nx-faint)]" title="{{ $st['label'] }}"></span>
                                @else
                                    <x-nx-badge :variant="$st['variant']" dot>{{ $st['label'] }}</x-nx-badge>
                                @endif
                            </span>
                            <span class="text-sm font-medium leading-snug text-[color:var(--nx-text)]">{{ $s['name'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>
            <div class="flex flex-col gap-3 border-t border-[color:var(--nx-line)] px-4 py-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Station {{ $station + 1 }} · {{ $current['name'] }}</div>
                    <div class="text-base font-medium text-[color:var(--nx-text)]">„{{ $current['question'] }}“</div>
                    <p class="m-0 text-sm text-[color:var(--nx-muted)]">{{ $current['description'] }}</p>
                    <p class="m-0 flex items-center gap-1.5 text-sm text-[color:var(--nx-text)]">
                        @svg('heroicon-o-document-magnifying-glass', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                        {{ $current['evidence'] }}
                    </p>
                </div>
                @if($current['tab'])
                    <x-nx-button variant="secondary" size="sm" wire:click="$parent.setTab('{{ $current['tab'] }}')">
                        Zum Bereich @svg('heroicon-o-arrow-right', 'w-4 h-4')
                    </x-nx-button>
                @endif
            </div>
        </x-nx-card>
        <p class="mt-2 mb-0 text-xs text-[color:var(--nx-faint)]">Nach Station 8 beginnt der Kreis von vorn – mit mehr Wissen als beim letzten Mal. Zustände werden aus den erfassten Daten abgeleitet; „Nicht erfasst“ heißt: dazu liegen keine Daten vor.</p>
    </x-nx-section>

    {{-- Kennzahlen --}}
    <x-nx-stat-grid :cols="5">
        <button type="button" wire:click="$parent.setTab('book')" class="text-left">
            <x-nx-stat label="Kapitel" :value="$i['chapters']['filled'] . ' / ' . $i['chapters']['total']" hint="des Books geschrieben" icon="heroicon-o-book-open" class="h-full hover:bg-[color:var(--nx-hover)]" />
        </button>
        <button type="button" wire:click="$parent.setTab('strategy')" class="text-left">
            <x-nx-stat label="Fokusräume" :value="$i['focus_areas']" hint="Empfehlung 5–7" icon="heroicon-o-viewfinder-circle"
                       :accent="$i['focus_areas'] > 7 ? 'var(--nx-warning)' : null" class="h-full hover:bg-[color:var(--nx-hover)]" />
        </button>
        <button type="button" wire:click="$parent.setTab('strategy')" class="text-left">
            <x-nx-stat label="Meilensteine" :value="$i['milestones']['reached_percent'] . ' %'"
                       :hint="$i['milestones']['reached'] . ' von ' . $i['milestones']['total'] . ' erreicht' . ($i['milestones']['overdue']->isNotEmpty() ? ' · ' . $i['milestones']['overdue']->count() . ' überfällig' : '')"
                       icon="heroicon-o-flag" :accent="$i['milestones']['overdue']->isNotEmpty() ? 'var(--nx-warning)' : null" class="h-full hover:bg-[color:var(--nx-hover)]" />
        </button>
        <button type="button" wire:click="$parent.setTab('risks')" class="text-left">
            <x-nx-stat label="Kritische / hohe Risiken" :value="$i['risks']['by_level']['critical'] . ' / ' . $i['risks']['by_level']['high']"
                       :hint="$i['risks']['open'] . ' offene Risiken'" icon="heroicon-o-exclamation-triangle"
                       :accent="$i['risks']['by_level']['critical'] > 0 ? 'var(--nx-danger)' : null" class="h-full hover:bg-[color:var(--nx-hover)]" />
        </button>
        <button type="button" wire:click="$parent.setTab('committees')" class="text-left">
            <x-nx-stat label="Offene Beschlüsse" :value="$i['open_decisions']"
                       :hint="$i['overdue_decisions']->count() ? $i['overdue_decisions']->count() . ' überfällig' : 'keine überfällig'"
                       icon="heroicon-o-check-badge" :accent="$i['overdue_decisions']->count() ? 'var(--nx-warning)' : null" class="h-full hover:bg-[color:var(--nx-hover)]" />
        </button>
    </x-nx-stat-grid>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        {{-- Orientierung --}}
        <div class="lg:col-span-3">
            <x-nx-section icon="heroicon-o-eye" title="Orientierung" description="Mission und Vision in Kurzform – vollständig im Book.">
                <x-slot name="action">
                    <x-nx-button variant="ghost" size="sm" wire:click="$parent.setTab('book')">Book öffnen</x-nx-button>
                </x-slot>
                <x-nx-card class="space-y-4">
                    @foreach(['mission' => 'Mission', 'vision' => 'Vision'] as $key => $label)
                        <div>
                            <div class="mb-1 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">
                                @svg($key === 'mission' ? 'heroicon-o-heart' : 'heroicon-o-eye', 'w-3.5 h-3.5') {{ $label }}
                            </div>
                            @if($i[$key] && filled($i[$key]->content))
                                <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-text)]">{{ $plain($i[$key]->content, 280) }}</p>
                            @else
                                <p class="m-0 text-sm text-[color:var(--nx-faint)]">Noch nicht formuliert.</p>
                            @endif
                        </div>
                    @endforeach
                </x-nx-card>
            </x-nx-section>
        </div>

        {{-- Risikoprofil --}}
        <div class="lg:col-span-2">
            <x-nx-section icon="heroicon-o-exclamation-triangle" title="Risikoprofil" :hint="$i['risks']['open']">
                <x-slot name="action">
                    <x-nx-button variant="ghost" size="sm" wire:click="$parent.setTab('risks')">Matrix</x-nx-button>
                </x-slot>
                <x-nx-card>
                    <div class="flex items-start gap-5">
                        @include('management-book::livewire.partials.risk-matrix', ['counts' => $i['risks']['matrix'], 'compact' => true])
                        <div class="min-w-0 flex-1 space-y-1.5 text-sm">
                            @foreach($levels->reverse() as $level)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-2 text-[color:var(--nx-muted)]">
                                        <span class="h-2.5 w-2.5 rounded-[3px]" style="background: {{ $level->tint() }};"></span>{{ $level->label() }}
                                    </span>
                                    <span class="font-medium tabular-nums text-[color:var(--nx-text)]">{{ $i['risks']['by_level'][$level->value] }}</span>
                                </div>
                            @endforeach
                            @if($i['risks']['unrated'])
                                <div class="flex items-center justify-between gap-2 text-[color:var(--nx-faint)]">
                                    <span>Unbewertet</span><span class="tabular-nums">{{ $i['risks']['unrated'] }}</span>
                                </div>
                            @endif
                            <div class="border-t border-[color:var(--nx-line)] pt-1.5 text-xs text-[color:var(--nx-muted)]">
                                Risikobereitschaft: <span class="text-[color:var(--nx-text)]">{{ $book->risk_appetite?->label() ?? 'nicht festgelegt' }}</span>
                            </div>
                        </div>
                    </div>
                </x-nx-card>
            </x-nx-section>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Nächste Sitzung --}}
        <x-nx-section icon="heroicon-o-calendar" title="Nächste Sitzung">
            @php $nm = $i['next_meeting']; @endphp
            @if($nm)
                <x-nx-card flush>
                    <x-nx-list-item icon="heroicon-o-calendar-days" :title="$nm->title"
                                    :subtitle="($nm->committee?->name ?? 'Gremium') . ($nm->location ? ' · ' . $nm->location : '')"
                                    :meta="$nm->scheduled_at->translatedFormat('D, d.m.Y · H:i')"
                                    :href="route('management-book.meetings.show', $nm)">
                        <x-slot name="trailing">
                            <x-nx-badge variant="info">in {{ (int) ceil(now()->floatDiffInDays($nm->scheduled_at)) }} Tg.</x-nx-badge>
                        </x-slot>
                    </x-nx-list-item>
                </x-nx-card>
                <p class="mt-2 mb-0 text-xs text-[color:var(--nx-faint)]">{{ $i['meetings_last_90'] }} Sitzung(en) in den letzten 90 Tagen durchgeführt.</p>
            @else
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-calendar">
                        Keine Sitzung geplant.
                        <x-slot name="action">
                            <x-nx-button variant="secondary" size="sm" wire:click="$parent.setTab('committees')">Zu den Gremien</x-nx-button>
                        </x-slot>
                    </x-nx-empty>
                </x-nx-card>
            @endif
        </x-nx-section>

        {{-- Was klemmt --}}
        @php $stuck = $i['milestones']['overdue']->count() + $i['overdue_decisions']->count() + ($i['review_due'] ? 1 : 0); @endphp
        <x-nx-section icon="heroicon-o-bolt" title="Was gerade klemmt" :hint="$stuck"
                      description="Überfälliges ist kein Versagen, sondern ein Anlass zu fragen: Was hindert – und wobei braucht es Hilfe?">
            @if($stuck === 0)
                <x-nx-card><x-nx-empty icon="heroicon-o-check-circle">Nichts überfällig.</x-nx-empty></x-nx-card>
            @else
                <x-nx-card flush>
                    <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                        @if($i['review_due'])
                            <li><x-nx-list-item icon="heroicon-o-arrow-path" title="Review des Books fällig" :subtitle="'seit ' . $book->next_review_at->format('d.m.Y')">
                                <x-slot name="trailing"><x-nx-badge variant="warning">Rückkopplung</x-nx-badge></x-slot>
                            </x-nx-list-item></li>
                        @endif
                        @foreach($i['milestones']['overdue']->take(5) as $m)
                            <li wire:key="od-ms-{{ $m->id }}">
                                <x-nx-list-item icon="heroicon-o-flag" :title="$m->title" :subtitle="$m->focusArea?->title" :meta="\Platform\ManagementBook\Services\BookInsights::milestoneWhen($m)">
                                    <x-slot name="trailing"><x-nx-badge variant="warning">Meilenstein</x-nx-badge></x-slot>
                                </x-nx-list-item>
                            </li>
                        @endforeach
                        @foreach($i['overdue_decisions']->take(5) as $d)
                            <li wire:key="od-dec-{{ $d->id }}">
                                <x-nx-list-item icon="heroicon-o-check-badge" :title="$d->text"
                                                :subtitle="$d->ownerUser?->name ?? $d->ownerAgent?->name ?? 'ohne Verantwortliche:n'"
                                                :meta="'fällig ' . $d->due_date->format('d.m.Y')"
                                                :href="route('management-book.meetings.show', $d->meeting_id)">
                                    <x-slot name="trailing"><x-nx-badge variant="warning">Beschluss</x-nx-badge></x-slot>
                                </x-nx-list-item>
                            </li>
                        @endforeach
                    </ul>
                </x-nx-card>
            @endif
        </x-nx-section>
    </div>
</div>
