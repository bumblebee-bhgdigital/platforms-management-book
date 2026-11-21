{{-- Ein Agenda-Punkt mit LA-Leitfaden (vier Fragen) und Handlungsfähigkeits-Kriterien. --}}
@php
    $checked = $item->outcome_checks ?? [];
    $guided = filled($item->goal) || filled($item->observation) || filled($item->obstacle) || filled($item->local_solution) || filled($item->system_support) || ! empty($checked);
    $verdict = $guided ? \Platform\ManagementBook\Enums\AgendaOutcomeCheck::verdict(count($checked)) : null;
    $questions = [
        ['goal', '1', 'Was wollten wir erreichen?'],
        ['observation', '2', 'Was beobachten wir tatsächlich?'],
        ['obstacle', '3', 'Was hindert euch momentan?'],
        ['local_solution', '4a', 'Was kann der Bereich selbst lösen?'],
        ['system_support', '4b', 'Wobei braucht er das Gesamtsystem?'],
    ];
@endphp
<x-nx-card flush class="mb-3" wire:key="agenda-{{ $item->id }}">
    <div x-data="{ open: {{ $guided ? 'true' : 'false' }} }">
        <div class="group flex items-start gap-3 px-4 py-3">
            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[color:var(--nx-accent-soft)] text-xs font-semibold tabular-nums text-[color:var(--nx-muted)]">{{ $idx + 1 }}</span>
            <button type="button" class="min-w-0 flex-1 text-left" @click="open = ! open">
                <div class="flex items-center gap-2">
                    <span class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $item->title }}</span>
                    @svg('heroicon-o-chevron-down', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)] transition-transform', ['x-bind:class' => "open ? 'rotate-180' : ''"])
                </div>
                <div class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-[color:var(--nx-muted)]">
                    @if($item->organization_entity_id)<span>{{ $entityNames[$item->organization_entity_id] ?? 'Einheit #' . $item->organization_entity_id }}</span>@endif
                    @if($item->ownerUser)<span>{{ $item->ownerUser->name }}</span>@endif
                    @if($item->duration_minutes)<span>{{ $item->duration_minutes }} Min.</span>@endif
                    @unless($guided)<span class="text-[color:var(--nx-faint)]">ohne Leitfaden</span>@endunless
                </div>
            </button>
            @if($verdict)
                <x-nx-badge :variant="$verdict['variant']" title="{{ count($checked) }} von 6 Kriterien">{{ count($checked) }}/6 · {{ $verdict['label'] }}</x-nx-badge>
            @endif
            @if($canEdit)
                <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                    @if($idx > 0)
                        <button type="button" wire:click="moveItem({{ $item->id }}, -1)" class="rounded p-1 text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" title="Nach oben">@svg('heroicon-o-arrow-up', 'w-4 h-4')</button>
                    @endif
                    @unless($last)
                        <button type="button" wire:click="moveItem({{ $item->id }}, 1)" class="rounded p-1 text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" title="Nach unten">@svg('heroicon-o-arrow-down', 'w-4 h-4')</button>
                    @endunless
                    <button type="button" wire:click="openItem({{ $item->id }})" class="rounded p-1 text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" title="Bearbeiten">@svg('heroicon-o-pencil-square', 'w-4 h-4')</button>
                    <button type="button" wire:click="deleteItem({{ $item->id }})" wire:confirm="Agenda-Punkt löschen?" class="rounded p-1 text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-danger)]" title="Löschen">@svg('heroicon-o-trash', 'w-4 h-4')</button>
                </div>
            @endif
        </div>

        <div x-show="open" x-cloak class="space-y-4 border-t border-[color:var(--nx-line)] px-4 py-4">
            @if($item->description)
                <p class="m-0 whitespace-pre-line text-sm text-[color:var(--nx-muted)]">{{ $item->description }}</p>
            @endif

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach($questions as [$field, $no, $question])
                    <div class="rounded-[6px] border border-[color:var(--nx-line)] p-3">
                        <div class="mb-1 flex items-center gap-1.5 text-xs font-medium text-[color:var(--nx-muted)]">
                            <span class="tabular-nums text-[color:var(--nx-faint)]">{{ $no }}</span> {{ $question }}
                        </div>
                        @if(filled($item->{$field}))
                            <div class="whitespace-pre-line text-sm text-[color:var(--nx-text)]">{{ $item->{$field} }}</div>
                        @else
                            <div class="text-sm text-[color:var(--nx-faint)]">–</div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if(blank($item->local_solution))
                <x-nx-callout variant="warning" icon="heroicon-o-exclamation-triangle">Ohne Antwort auf 4a droht Mikromanagement: Was bleibt beim Bereich?</x-nx-callout>
            @endif
            @if(blank($item->system_support))
                <x-nx-callout variant="info" icon="heroicon-o-information-circle">Wenn das Gesamtsystem nichts beitragen muss – gehört der Punkt überhaupt in den LA?</x-nx-callout>
            @endif

            <div>
                <div class="mb-2 text-xs font-medium text-[color:var(--nx-muted)]">Nach dem LA – was trifft zu?</div>
                <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                    @foreach($checks as $check)
                        @php $on = in_array($check->value, $checked, true); @endphp
                        <button type="button" @if($canEdit) wire:click="toggleCheck({{ $item->id }}, '{{ $check->value }}')" @endif
                                class="flex items-center gap-2 rounded-[6px] px-2 py-1.5 text-left text-sm transition-colors {{ $canEdit ? 'hover:bg-[color:var(--nx-hover)]' : 'cursor-default' }} {{ $on ? 'text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)]' }}">
                            @svg($on ? 'heroicon-s-check-circle' : 'heroicon-o-stop', 'w-4 h-4 shrink-0 ' . ($on ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-faint)]'))
                            {{ $check->label() }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if($canEdit)
                <div class="flex gap-2">
                    <x-nx-button variant="ghost" size="sm" wire:click="openItem({{ $item->id }})">@svg('heroicon-o-pencil-square', 'w-4 h-4') Leitfaden bearbeiten</x-nx-button>
                    <x-nx-button variant="ghost" size="sm" wire:click="openDecision(null, {{ $item->id }})">@svg('heroicon-o-clipboard-document-check', 'w-4 h-4') Beschluss zu diesem Punkt</x-nx-button>
                </div>
            @endif
        </div>
    </div>
</x-nx-card>
