{{-- Tab „Gremien“: Einstieg in die Rolle der Gremien, Gremien als Karten, Gremien-Knoten zum Aktivieren, Startvorschläge im Leerzustand. --}}
<div class="space-y-8">
    {{-- Einstieg: wie die Gremien im Regelkreis zusammenspielen --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="max-w-2xl space-y-2">
            <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Gremien im Regelkreis</div>
            <h2 class="m-0 text-xl font-semibold text-[color:var(--nx-text)]">Richtung geben, Zusammenspiel regulieren</h2>
            <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                Das <strong class="font-medium text-[color:var(--nx-text)]">Strategiemeeting</strong> der Geschäftsführung klärt, wohin es langfristig geht, und speist das Book.
                Der <strong class="font-medium text-[color:var(--nx-text)]">Lenkungsausschuss</strong> beobachtet die Umsetzung und löst, was einzelne Einheiten allein nicht lösen können.
                Was er lernt, fließt zurück in die nächste Strategiearbeit.
            </p>
        </div>
        @if($canEdit && $committees->isNotEmpty())
            <x-nx-button variant="secondary" size="sm" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-4 h-4') Gremium
            </x-nx-button>
        @endif
    </div>

    {{-- Mini-Schleife: Strategiemeeting -> Book -> Lenkungsausschuss -> zurück --}}
    <div class="flex flex-wrap items-center gap-2 text-xs text-[color:var(--nx-muted)]">
        <span class="rounded-full border border-[color:var(--nx-line)] px-2.5 py-1">Strategiemeeting</span>
        @svg('heroicon-o-arrow-long-right', 'w-4 h-4 text-[color:var(--nx-faint)]')
        <span class="rounded-full border border-[color:var(--nx-line)] px-2.5 py-1">Management Book</span>
        @svg('heroicon-o-arrow-long-right', 'w-4 h-4 text-[color:var(--nx-faint)]')
        <span class="rounded-full border border-[color:var(--nx-line)] px-2.5 py-1">Umsetzung</span>
        @svg('heroicon-o-arrow-long-right', 'w-4 h-4 text-[color:var(--nx-faint)]')
        <span class="rounded-full border border-[color:var(--nx-line)] px-2.5 py-1">Lenkungsausschuss</span>
        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4 text-[color:var(--nx-faint)]')
        <span>Rückkopplung</span>
    </div>

    @if($committees->isEmpty())
        <x-nx-card>
            <div class="space-y-5 p-2">
                <div class="space-y-1">
                    <h3 class="m-0 text-base font-semibold text-[color:var(--nx-text)]">Noch keine Gremien</h3>
                    <p class="m-0 max-w-2xl text-sm leading-relaxed text-[color:var(--nx-muted)]">
                        Die meisten Books starten mit zwei Gremien. Wähle einen Vorschlag – Name, Typ, Rhythmus und Zweck sind vorbelegt und lassen sich vor dem Anlegen anpassen.
                    </p>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach([
                        'strategy_meeting' => ['icon' => 'heroicon-o-light-bulb', 'title' => 'Strategiemeeting GF anlegen', 'text' => 'Die Geschäftsführung klärt Grundsatzfragen und gibt die Richtung. Typisch halbjährlich.'],
                        'steering_committee' => ['icon' => 'heroicon-o-adjustments-horizontal', 'title' => 'Lenkungsausschuss anlegen', 'text' => 'Reguliert, was zwischen den Einheiten klemmt – lokal vor übergreifend. Typisch monatlich.'],
                    ] as $key => $p)
                        @if($canEdit)
                            <button type="button" wire:click="openCreate('{{ $key }}')"
                                    class="group flex items-start gap-3 rounded-[8px] border border-[color:var(--nx-line)] p-4 text-left transition-colors hover:bg-[color:var(--nx-hover)]">
                                @svg($p['icon'], 'mt-0.5 w-5 h-5 shrink-0 text-[color:var(--nx-muted)]')
                                <span class="space-y-1">
                                    <span class="block text-sm font-medium text-[color:var(--nx-text)]">{{ $p['title'] }}</span>
                                    <span class="block text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $p['text'] }}</span>
                                </span>
                            </button>
                        @else
                            <div class="flex items-start gap-3 rounded-[8px] border border-[color:var(--nx-line)] p-4">
                                @svg($p['icon'], 'mt-0.5 w-5 h-5 shrink-0 text-[color:var(--nx-muted)]')
                                <span class="block text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $p['text'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
                @if($canEdit)
                    <button type="button" wire:click="openCreate" class="text-sm text-[color:var(--nx-muted)] underline hover:text-[color:var(--nx-text)]">Anderes Gremium anlegen</button>
                @endif
            </div>
        </x-nx-card>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($committees as $committee)
                @php
                    $next = $nextMeetings[$committee->id] ?? null;
                    $dec = $openDecisions[$committee->id] ?? null;
                @endphp
                <a wire:key="committee-{{ $committee->id }}" href="{{ route('management-book.committees.show', $committee) }}" wire:navigate
                   class="group flex flex-col gap-4 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-5 transition-colors hover:bg-[color:var(--nx-hover)]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Book\Committees::typeBadge($committee->type)">{{ $committee->type?->label() }}</x-nx-badge>
                                @if($committee->status?->value !== 'active')
                                    <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Book\Committees::statusBadge($committee->status)">{{ $committee->status?->label() }}</x-nx-badge>
                                @endif
                            </div>
                            <h3 class="m-0 truncate text-base font-semibold text-[color:var(--nx-text)]">{{ $committee->name }}</h3>
                        </div>
                        @svg('heroicon-o-arrow-right', 'mt-1 w-4 h-4 shrink-0 text-[color:var(--nx-faint)] transition-transform group-hover:translate-x-0.5')
                    </div>

                    <p class="m-0 line-clamp-2 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                        {{ $committee->description ?: 'Noch kein Zweck beschrieben – wofür ist dieses Gremium da, und was gehört nicht hierher?' }}
                    </p>
                    <p class="m-0 text-xs leading-relaxed text-[color:var(--nx-faint)]">
                        <span class="font-medium text-[color:var(--nx-muted)]">Format {{ $committee->format()->name() }}:</span> {{ $committee->format()->question() }}
                    </p>

                    <dl class="m-0 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-[color:var(--nx-faint)]">Vorsitz</dt>
                            <dd class="m-0 truncate text-[color:var(--nx-text)]">{{ $chairs[$committee->id]['name'] ?? 'offen' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[color:var(--nx-faint)]">Rhythmus</dt>
                            <dd class="m-0 truncate text-[color:var(--nx-text)]">{{ $committee->cadence ?: '–' }} · {{ $people[$committee->id]->count() }} {{ $people[$committee->id]->count() === 1 ? 'Mitglied' : 'Mitglieder' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[color:var(--nx-faint)]">Nächste Sitzung</dt>
                            <dd class="m-0 text-[color:var(--nx-text)]">{{ $next ? $next->scheduled_at?->format('d.m.Y, H:i') : 'keine geplant' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[color:var(--nx-faint)]">Offene Beschlüsse</dt>
                            <dd class="m-0">
                                @if($dec && $dec->n > 0)
                                    <x-nx-badge :variant="$dec->overdue > 0 ? 'danger' : 'warning'">
                                        {{ $dec->n }} offen{{ $dec->overdue > 0 ? ' · ' . $dec->overdue . ' überfällig' : '' }}
                                    </x-nx-badge>
                                @else
                                    <span class="text-[color:var(--nx-text)]">keine</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </a>
            @endforeach
        </div>
    @endif

    @if($suggestions->isNotEmpty())
        <section class="space-y-3">
            <div class="max-w-2xl space-y-1">
                <h3 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Gremien-Knoten in der Organisation</h3>
                <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                    Diese Gremien gibt es in der Organisation schon, im Book arbeiten sie noch nicht. Aktivieren macht den Knoten zum Gremium – Name, Beschreibung und Mitglieder bleiben dort, das Book ergänzt Format, Sitzungen und Beschlüsse.
                </p>
            </div>
            <x-nx-card flush>
                @foreach($suggestions as $s)
                    <div class="flex items-center gap-3 border-b border-[color:var(--nx-line)] px-5 py-3 last:border-b-0" wire:key="sugg-{{ $s->id }}">
                        @svg('heroicon-o-user-group', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-[color:var(--nx-text)]">{{ $s->name }}</span>
                            @if($s->parent)<span class="block truncate text-xs text-[color:var(--nx-faint)]">unter {{ $s->parent->name }}</span>@endif
                        </span>
                        @if($canEdit)
                            <x-nx-button variant="secondary" size="sm" wire:click="openCreate(null, {{ $s->id }})">Aktivieren</x-nx-button>
                        @endif
                    </div>
                @endforeach
            </x-nx-card>
        </section>
    @endif

    <x-nx-modal wire:model="showCreate" size="md">
        <x-slot name="header">Gremium anlegen</x-slot>
        <div class="space-y-4">
            @if($nodesAvailable)
                <p class="m-0 text-xs leading-relaxed text-[color:var(--nx-muted)]">
                    @if($form['entity_id'])
                        Das Gremium ist der vorhandene Knoten der Organisation – Name, Zweck und Mitglieder bleiben dort.
                    @else
                        Das Gremium wird als Knoten in der Organisation angelegt (unter „Governance“ der Einheit dieses Books). Name, Zweck und Mitglieder liegen dort.
                    @endif
                </p>
            @endif
            @error('form.entity_id')<p class="m-0 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>@enderror
            <x-nx-input-text name="form.name" label="Name" wire:model="form.name" placeholder="z. B. BHG.DIGITAL Lenkungsausschuss" required :disabled="(bool) $form['entity_id']" />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-nx-input-select name="form.type" label="Typ" :options="$typeOptions" wire:model="form.type" />
                <x-nx-input-text name="form.cadence" label="Rhythmus" wire:model="form.cadence" placeholder="z. B. monatlich" />
            </div>
            <x-nx-input-select name="form.chair_user_id" label="Vorsitz" :options="$userOptions" nullable="true" nullLabel="– offen –" wire:model="form.chair_user_id" />
            <x-nx-input-textarea name="form.description" label="Zweck" wire:model="form.description" rows="3" hint="Wofür ist das Gremium da – und was gehört nicht hierher?" :disabled="(bool) $form['entity_id']" />
            <p class="m-0 text-xs leading-relaxed text-[color:var(--nx-muted)]">Das Gremium enthält keine Ablaufregeln. Agenda und Arbeitsweise legt ihr in den Sitzungen fest.</p>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showCreate', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="create">Anlegen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</div>
