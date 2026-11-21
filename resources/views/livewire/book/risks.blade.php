{{--
    Bereich „Risiken“ (Risikomatrix-Vorlage + Risiko-Skala). Oben: Risikobereitschaft und Lagebild (Heatmap + Ampel-Verteilung),
    darunter das Register. Farbe nur für die Ampel (bedeutungstragend).
--}}
@php
    $levelOptions = array_map(fn ($l) => ['value' => $l->value, 'label' => $l->label()], \Platform\ManagementBook\Enums\RiskLevel::cases());
    $statusOptions = array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], \Platform\ManagementBook\Enums\RiskStatus::cases());
@endphp
<div class="space-y-8">
    @if(session('risk_error'))
        <x-nx-callout variant="danger" icon="heroicon-o-exclamation-triangle" title="Fehler">{{ session('risk_error') }}</x-nx-callout>
    @endif

    {{-- Einstieg --}}
    <div class="max-w-3xl space-y-2">
        <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Risikomatrix</div>
        <h2 class="m-0 text-xl font-semibold tracking-tight text-[color:var(--nx-text)]">Was kann uns auf dem Weg treffen?</h2>
        <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
            Jedes Risiko bekommt zwei Einschätzungen: die <span class="font-medium text-[color:var(--nx-text)]">Eintrittswahrscheinlichkeit E</span> (1 sehr unwahrscheinlich bis 5 sehr wahrscheinlich)
            und die <span class="font-medium text-[color:var(--nx-text)]">Auswirkung A</span> (1 kaum Einfluss bis 5 existenzbedrohend). E × A ergibt die Priorität und damit die Ampel.
            Schätzt lieber grob und gemeinsam als genau und allein – die Matrix soll Prioritäten besprechbar machen, nicht Scheingenauigkeit erzeugen.
        </p>
        <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
            Rote und orange Risiken gehören auf die Agenda des Lenkungsausschusses – mit der Frage, was der verantwortliche Bereich selbst tun kann und wobei er das Gesamtsystem braucht.
        </p>
    </div>

    {{-- Lagebild --}}
    <x-nx-section icon="heroicon-o-squares-2x2" title="Lagebild" :hint="$totalActive . ' aktiv'"
                  description="Jedes Feld der Matrix zeigt, wie viele offene Risiken dort liegen. Ein Klick auf ein Feld oder eine Stufe filtert das Register darunter.">
        @if($canEdit)
            <x-slot name="action">
                <x-nx-button variant="primary" size="sm" wire:click="openCreate">@svg('heroicon-o-plus', 'w-4 h-4') Risiko erfassen</x-nx-button>
            </x-slot>
        @endif
        <x-nx-card class="p-6">
            <div class="flex flex-col gap-10 xl:flex-row xl:items-start">
                <div class="shrink-0">
                    @include('management-book::livewire.partials.risk-matrix', ['counts' => $counts, 'clickable' => true, 'selected' => $cell, 'size' => 'lg'])
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="mb-2 text-xs font-medium text-[color:var(--nx-muted)]">Ampel nach Priorität – und was sie verlangt</div>
                    @foreach(array_reverse(\Platform\ManagementBook\Enums\RiskLevel::cases()) as $level)
                        @php $n = $byLevel[$level->value] ?? 0; @endphp
                        <button type="button" wire:click="$set('levelFilter', '{{ $levelFilter === $level->value ? '' : $level->value }}')"
                                wire:key="lvl-{{ $level->value }}"
                                class="flex w-full items-center gap-3 rounded-[8px] px-3 py-3 text-left transition-colors hover:bg-[color:var(--nx-hover)] {{ $levelFilter === $level->value ? 'bg-[color:var(--nx-active)]' : '' }}">
                            <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $level->tint() }}; box-shadow: inset 0 0 0 1px var(--nx-line-strong);"></span>
                            <span class="w-20 text-sm font-medium text-[color:var(--nx-text)]">{{ $level->label() }}</span>
                            <span class="w-14 text-xs tabular-nums text-[color:var(--nx-faint)]">{{ match($level) { \Platform\ManagementBook\Enums\RiskLevel::LOW => '1–4', \Platform\ManagementBook\Enums\RiskLevel::MODERATE => '5–9', \Platform\ManagementBook\Enums\RiskLevel::HIGH => '10–15', \Platform\ManagementBook\Enums\RiskLevel::CRITICAL => '16–25' } }}</span>
                            <span class="min-w-0 flex-1 text-sm text-[color:var(--nx-muted)]">{{ $level->guidance() }}</span>
                            <span class="w-8 text-right text-xl font-semibold tabular-nums {{ $n > 0 ? 'text-[color:var(--nx-text)]' : 'text-[color:var(--nx-faint)]' }}">{{ $n }}</span>
                        </button>
                    @endforeach
                    <div class="flex items-center justify-between border-t border-[color:var(--nx-line)] px-2 pt-2 text-xs text-[color:var(--nx-muted)]">
                        <span>Unbewertet (E oder A fehlt)</span>
                        <span class="tabular-nums">{{ $unrated }}</span>
                    </div>
                    @if($closedCount > 0)
                        <div class="flex items-center justify-between px-2 text-xs text-[color:var(--nx-muted)]">
                            <span>Geschlossen (nicht im Lagebild)</span>
                            <span class="tabular-nums">{{ $closedCount }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </x-nx-card>
    </x-nx-section>

    {{-- Risikobereitschaft --}}
    <x-nx-section icon="heroicon-o-scale" title="Risikobereitschaft"
                  :hint="$appetite?->label() ?? 'nicht festgelegt'"
                  description="Wie viel Unsicherheit trägt dieses Book bewusst? Die Stufe ist der Maßstab, an dem sich Entscheidungen und Maßnahmen messen – eine hohe Bereitschaft heißt nicht leichtsinnig, sondern bewusst.">
        @if($appetite)
            @php $prof = $appetite->profile(); @endphp
            <div class="mb-3 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-4 py-3">
                <div class="text-sm font-semibold text-[color:var(--nx-text)]">{{ $appetite->label() }}: {{ $prof[0] }}.</div>
                <p class="mt-1 mb-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                    Für Entscheidungen heißt das: {{ $prof[1] }}. {{ $prof[2] }}. Akzeptiertes Maß an Unsicherheit: {{ strtolower($prof[3]) }}.
                </p>
            </div>
        @else
            <div class="mb-3 rounded-[8px] border border-dashed border-[color:var(--nx-line-strong)] px-4 py-3 text-sm text-[color:var(--nx-muted)]">
                Noch keine Stufe festgelegt. {{ $canEdit ? 'Wählt in der Kopfzeile der Tabelle die Stufe, die zu eurer Lage passt – am besten gemeinsam im Strategiemeeting.' : '' }}
            </div>
        @endif
        <x-nx-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] table-fixed border-collapse text-sm">
                    <thead>
                        <tr>
                            <th class="w-44 px-3 py-2"></th>
                            @foreach(\Platform\ManagementBook\Enums\RiskAppetite::cases() as $a)
                                @php $on = $appetite === $a; @endphp
                                <th class="px-2 py-2 text-left align-bottom" wire:key="app-h-{{ $a->value }}">
                                    @if($canEdit)
                                        <button type="button" wire:click="setAppetite('{{ $on ? '' : $a->value }}')"
                                                class="w-full rounded-[6px] px-2 py-1.5 text-left text-sm font-semibold transition-colors {{ $on ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}"
                                                title="{{ $on ? 'Auswahl aufheben' : 'Als Risikobereitschaft festlegen' }}">
                                            {{ $a->label() }}
                                        </button>
                                    @else
                                        <span class="block rounded-[6px] px-2 py-1.5 text-sm font-semibold {{ $on ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'text-[color:var(--nx-text)]' }}">{{ $a->label() }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(\Platform\ManagementBook\Enums\RiskAppetite::profileDimensions() as $d => $dimension)
                            <tr class="border-t border-[color:var(--nx-line)]">
                                <th class="px-3 py-2 text-left align-top text-xs font-semibold text-[color:var(--nx-text)]">{{ $dimension }}</th>
                                @foreach(\Platform\ManagementBook\Enums\RiskAppetite::cases() as $a)
                                    <td class="px-4 py-2 align-top text-xs leading-relaxed {{ $appetite === $a ? 'bg-[color:var(--nx-active)] text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)]' }}">
                                        {{ $a->profile()[$d] }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-nx-card>
    </x-nx-section>

    {{-- Register --}}
    <x-nx-section icon="heroicon-o-table-cells" title="Risikoregister" :hint="$risks->count()"
                  description="Alle Risiken des Books, das höchste zuerst. Zeile anklicken, um Bewertung, Maßnahmen oder Status zu ändern.">
        <x-slot name="action">
            <div class="flex flex-wrap items-center gap-2">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Suchen …"
                       class="h-8 w-40 rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-2.5 text-sm text-[color:var(--nx-text)] placeholder-[color:var(--nx-faint)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]" />
                <x-nx-input-select name="statusFilter" size="sm" :options="$statusOptions" nullable="true" nullLabel="Alle Status" wire:model.live="statusFilter" />
                <x-nx-input-select name="levelFilter" size="sm" :options="$levelOptions" nullable="true" nullLabel="Alle Stufen" wire:model.live="levelFilter" />
                @if($categories->isNotEmpty())
                    <x-nx-input-select name="categoryFilter" size="sm" :options="$categories->all()" nullable="true" nullLabel="Alle Kategorien" wire:model.live="categoryFilter" />
                @endif
                <label class="inline-flex items-center gap-1.5 text-xs text-[color:var(--nx-muted)]">
                    <input type="checkbox" wire:model.live="showClosed" class="rounded border-[color:var(--nx-line-strong)]"> Geschlossene
                </label>
                @if($hasFilters)
                    <x-nx-button variant="ghost" size="sm" wire:click="clearFilters">Filter zurücksetzen</x-nx-button>
                @endif
            </div>
        </x-slot>

        @if($cell)
            <div class="mb-2 text-xs text-[color:var(--nx-muted)]">
                Gefiltert auf Feld E {{ $cell[0] }} × A {{ $cell[1] }} = {{ $cell[0] * $cell[1] }}
                <button type="button" wire:click="filterCell({{ $cell[0] }}, {{ $cell[1] }})" class="ml-1 underline hover:text-[color:var(--nx-text)]">aufheben</button>
            </div>
        @endif

        @if($totalAll === 0)
            <x-nx-card class="p-6">
                <div class="mx-auto max-w-2xl space-y-4 text-center">
                    @svg('heroicon-o-shield-exclamation', 'mx-auto w-8 h-8 text-[color:var(--nx-faint)]')
                    <div>
                        <div class="text-base font-semibold text-[color:var(--nx-text)]">Noch keine Risiken erfasst</div>
                        <p class="mx-auto mt-1 mb-0 max-w-lg text-sm leading-relaxed text-[color:var(--nx-muted)]">
                            Jede Zeile der Risikomatrix-Vorlage wird hier ein Risiko. So sieht ein Eintrag aus:
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-4 py-3 text-left text-sm">
                        <span class="font-medium text-[color:var(--nx-text)]">Lieferengpass bei Frischeprodukten</span>
                        <span class="text-[color:var(--nx-faint)]">·</span>
                        <span class="text-[color:var(--nx-muted)]">Einkauf / Logistik</span>
                        <span class="text-[color:var(--nx-faint)]">·</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums text-[color:var(--nx-text)]" style="background: {{ \Platform\ManagementBook\Enums\RiskLevel::HIGH->tint() }};">E 4 × A 3 = 12</span>
                        <span class="text-[color:var(--nx-muted)]">Maßnahmen umsetzen, überwachen</span>
                    </div>
                    @if($canEdit)
                        <x-nx-button variant="primary" wire:click="openCreate">@svg('heroicon-o-plus', 'w-4 h-4') Erstes Risiko erfassen</x-nx-button>
                    @endif
                </div>
            </x-nx-card>
        @elseif($risks->isEmpty())
            <x-nx-card><x-nx-empty icon="heroicon-o-funnel">Keine Risiken für diese Filter.</x-nx-empty></x-nx-card>
        @else
            <x-nx-card flush>
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Nr.</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Risiko</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Bereich</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">E</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">A</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Priorität</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Kategorie</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Verantwortlich</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Frist</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Status</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach($risks as $risk)
                            @php $level = $risk->level(); @endphp
                            <x-nx-table-row wire:key="risk-{{ $risk->id }}" wire:click="openEdit({{ $risk->id }})" class="cursor-pointer {{ $risk->status === \Platform\ManagementBook\Enums\RiskStatus::CLOSED ? 'opacity-60' : '' }}">
                                <x-nx-table-cell><span class="tabular-nums text-[color:var(--nx-muted)]">{{ $risk->number ?? '–' }}</span></x-nx-table-cell>
                                <x-nx-table-cell>
                                    <div class="max-w-xs">
                                        <div class="truncate font-medium text-[color:var(--nx-text)]">{{ $risk->title }}</div>
                                        @if($risk->measures_planned || $risk->measures_implemented)
                                            <div class="truncate text-xs text-[color:var(--nx-faint)]">
                                                {{ $risk->measures_implemented ? 'Umgesetzt: ' . $risk->measures_implemented : 'Geplant: ' . $risk->measures_planned }}
                                            </div>
                                        @endif
                                    </div>
                                </x-nx-table-cell>
                                <x-nx-table-cell><span class="text-xs">{{ $risk->entity?->name ?? '–' }}</span></x-nx-table-cell>
                                <x-nx-table-cell align="right"><span class="tabular-nums">{{ $risk->probability ?? '–' }}</span></x-nx-table-cell>
                                <x-nx-table-cell align="right"><span class="tabular-nums">{{ $risk->impact ?? '–' }}</span></x-nx-table-cell>
                                <x-nx-table-cell>
                                    @if($level)
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums text-[color:var(--nx-text)]" style="background: {{ $level->tint() }};">
                                            {{ $risk->score }} · {{ $level->label() }}
                                        </span>
                                    @else
                                        <span class="text-xs text-[color:var(--nx-faint)]">unbewertet</span>
                                    @endif
                                </x-nx-table-cell>
                                <x-nx-table-cell><span class="text-xs">{{ $risk->category ?? '–' }}</span></x-nx-table-cell>
                                <x-nx-table-cell><span class="text-xs">{{ $risk->owner?->name ?? $risk->ownerAgent?->name ?? '–' }}</span></x-nx-table-cell>
                                <x-nx-table-cell>
                                    @if($risk->due_date)
                                        <span class="text-xs tabular-nums {{ $risk->due_date->isPast() && $risk->status !== \Platform\ManagementBook\Enums\RiskStatus::CLOSED ? 'font-medium text-[color:var(--nx-danger)]' : '' }}">{{ $risk->due_date->format('d.m.Y') }}</span>
                                    @else
                                        <span class="text-xs text-[color:var(--nx-faint)]">–</span>
                                    @endif
                                </x-nx-table-cell>
                                <x-nx-table-cell><x-nx-badge :variant="$risk->status->badgeVariant()" dot>{{ $risk->status->label() }}</x-nx-badge></x-nx-table-cell>
                            </x-nx-table-row>
                        @endforeach
                    </x-nx-table-body>
                </x-nx-table>
            </x-nx-card>
        @endif
    </x-nx-section>

    {{-- Anlegen / Bearbeiten --}}
    <x-nx-modal wire:model="showModal" size="lg">
        <x-slot name="header">
            <div class="text-base font-semibold">{{ $editingId ? 'Risiko bearbeiten' : 'Risiko erfassen' }}</div>
            <div class="text-xs text-[color:var(--nx-muted)]">Felder wie in der Risikomatrix-Vorlage. Priorität und Ampel werden berechnet.</div>
        </x-slot>

        @if($showModal)
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[6rem_1fr]">
                    <x-nx-input-text name="form.number" label="Nr." wire:model="form.number" />
                    <x-nx-input-text name="form.title" label="Risikobezeichnung" wire:model="form.title" required placeholder="z. B. Lieferengpass bei Frischeprodukten" />
                </div>
                <x-nx-input-textarea name="form.description" label="Beschreibung" wire:model="form.description" rows="2" />

                {{-- Bewertung --}}
                <div class="rounded-[8px] border border-[color:var(--nx-line)] p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-nx-input-select name="form.probability" label="Eintrittswahrscheinlichkeit (E)" nullable="true" nullLabel="– nicht bewertet –"
                                           :options="collect(\Platform\ManagementBook\Enums\RiskLevel::probabilityLabels())->map(fn ($l, $v) => ['value' => $v, 'label' => $v . ' · ' . $l])->values()->all()"
                                           wire:model.live="form.probability" />
                        <x-nx-input-select name="form.impact" label="Auswirkung (A)" nullable="true" nullLabel="– nicht bewertet –"
                                           :options="collect(\Platform\ManagementBook\Enums\RiskLevel::impactLabels())->map(fn ($l, $v) => ['value' => $v, 'label' => $v . ' · ' . $l])->values()->all()"
                                           wire:model.live="form.impact" />
                    </div>
                    <div class="mt-3 flex items-center gap-3 rounded-[6px] px-3 py-2"
                         style="background: {{ $preview ? $preview['level']->tint() : 'var(--nx-hover)' }};">
                        @if($preview)
                            <span class="text-2xl font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $preview['score'] }}</span>
                            <div class="text-sm">
                                <div class="font-semibold text-[color:var(--nx-text)]">Risikopriorität {{ $preview['level']->label() }}</div>
                                <div class="text-xs text-[color:var(--nx-muted)]">{{ $preview['level']->guidance() }}</div>
                            </div>
                        @else
                            <span class="text-sm text-[color:var(--nx-muted)]">E und A wählen, um Priorität und Ampel zu sehen.</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {{-- Bereich / Abteilung --}}
                    <div>
                        <div class="mb-1 text-xs font-medium text-[color:var(--nx-text)]">Bereich / Abteilung</div>
                        @if($selectedEntity)
                            <div class="flex items-center gap-2">
                                <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-bg)] py-1 pl-3 pr-1 text-sm text-[color:var(--nx-text)]">
                                    @svg('heroicon-o-building-office', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                                    <span class="truncate">{{ $selectedEntity->name }}</span>
                                    <button type="button" wire:click="chooseEntity(null)" title="Zuordnung entfernen"
                                            class="rounded-full p-0.5 text-[color:var(--nx-faint)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]">@svg('heroicon-o-x-mark', 'w-3.5 h-3.5')</button>
                                </span>
                            </div>
                        @else
                            <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false" class="relative">
                                <input type="search" wire:model.live.debounce.250ms="entitySearch" placeholder="Bereich suchen, z. B. Einkauf …"
                                       @focus="open = true" @input="open = true"
                                       class="block w-full rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-3 py-2 text-sm text-[color:var(--nx-text)] placeholder-[color:var(--nx-faint)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]" />
                                <div x-show="open" x-cloak style="display:none"
                                     class="absolute left-0 right-0 z-50 mt-1 overflow-hidden rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] py-1 shadow-[var(--nx-shadow-pop)]">
                                    @forelse($entityOptions as $entity)
                                        <button type="button" wire:key="ent-{{ $entity->id }}" wire:click="chooseEntity({{ $entity->id }})" @click="open = false"
                                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-[color:var(--nx-hover)] {{ $entity->is_active === false ? 'text-[color:var(--nx-faint)]' : 'text-[color:var(--nx-text)]' }}">
                                            <span class="truncate">{{ $entity->name }}</span>
                                            @if($entity->type)<span class="ml-auto shrink-0 text-xs text-[color:var(--nx-faint)]">{{ $entity->type->name }}</span>@endif
                                        </button>
                                    @empty
                                        <div class="px-3 py-1.5 text-xs text-[color:var(--nx-muted)]">Kein passender Knoten.</div>
                                    @endforelse
                                </div>
                            </div>
                            <p class="mt-1 mb-0 text-xs text-[color:var(--nx-faint)]">Wo das Risiko entsteht – die Einheit, die es zuerst lösen sollte.</p>
                        @endif
                        @error('form.organization_entity_id')<p class="mt-1 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-3">
                        <x-nx-input-text name="form.category" label="Risikokategorie" wire:model="form.category" placeholder="z. B. Operatives Risiko" list="mb-risk-categories" />
                        <datalist id="mb-risk-categories">
                            @foreach($categories as $c)<option value="{{ $c }}">@endforeach
                        </datalist>
                        <x-nx-input-select name="form.owner_user_id" label="Verantwortlich" nullable="true" nullLabel="– niemand –"
                                           :options="$members->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all()"
                                           wire:model="form.owner_user_id" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-nx-input-textarea name="form.measures_planned" label="Maßnahmen geplant" wire:model="form.measures_planned" rows="3" />
                    <x-nx-input-textarea name="form.measures_implemented" label="Maßnahmen umgesetzt" wire:model="form.measures_implemented" rows="3" />
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <x-nx-input-date name="form.due_date" label="Frist" wire:model="form.due_date" />
                    <x-nx-input-select name="form.status" label="Status" :options="$statusOptions" wire:model="form.status" />
                    <x-nx-input-date name="form.review_date" label="Nächste Überprüfung" wire:model="form.review_date" />
                </div>
            </div>
        @endif

        <x-slot name="footer">
            <div class="flex w-full items-center justify-between gap-3">
                <div class="flex gap-2">
                    @if($editingId && $canEdit)
                        <x-nx-button variant="danger" wire:click="deleteRisk({{ $editingId }})" wire:confirm="Risiko löschen? (Soft-Delete)">Löschen</x-nx-button>
                        @if(($form['status'] ?? null) !== \Platform\ManagementBook\Enums\RiskStatus::CLOSED->value)
                            <x-nx-button variant="ghost" wire:click="closeRisk({{ $editingId }})">Schließen</x-nx-button>
                        @endif
                    @endif
                </div>
                <div class="flex gap-2">
                    <x-nx-button variant="ghost" wire:click="$set('showModal', false)">Abbrechen</x-nx-button>
                    @if($canEdit)
                        <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
                    @endif
                </div>
            </div>
        </x-slot>
    </x-nx-modal>
</div>
