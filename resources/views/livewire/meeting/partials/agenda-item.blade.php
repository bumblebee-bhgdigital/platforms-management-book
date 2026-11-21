{{-- Ein Agenda-Punkt mit dem Leitfaden des Formats (LA: vier Fragen; Strategie: Ausgangslage/Richtungsfrage; Board: Lage/Vorlage/Abweichung) und dessen Erfolgskriterien. --}}
@php
    $isInfo = $item->kind === \Platform\ManagementBook\Formats\CommitteeFormat::INFO;
    $checked = $format->checksOf($item);
    $checkMax = count($checks);
    $guided = $format->isGuided($item);
    $verdict = $guided ? $format->verdict(count($checked), $checkMax) : null;
    $fields = $format->agendaFields();
    $hints = $format->itemHints($item);
@endphp
<x-nx-card flush wire:key="agenda-{{ $item->id }}">
    <div x-data="{ open: {{ $guided ? 'true' : 'false' }} }">
        <div class="group flex items-start gap-4 px-5 py-4">
            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[color:var(--nx-accent-soft)] text-xs font-semibold tabular-nums text-[color:var(--nx-muted)]">{{ $idx + 1 }}</span>
            <button type="button" class="min-w-0 flex-1 text-left" @click="open = ! open">
                <div class="flex items-center gap-2">
                    <span class="truncate text-[15px] font-semibold text-[color:var(--nx-text)]">{{ $item->title }}</span>
                    @svg('heroicon-o-chevron-down', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)] transition-transform', ['x-bind:class' => "open ? 'rotate-180' : ''"])
                </div>
                <div class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-[color:var(--nx-muted)]">
                    <span class="font-medium text-[color:var(--nx-text)]">{{ $format->agendaKindLabel($item->kind) }}</span>
                    @if($item->organization_entity_id)<span>{{ $entityNames[$item->organization_entity_id] ?? 'Einheit #' . $item->organization_entity_id }}</span>@endif
                    @if($item->ownerUser)<span>{{ $item->ownerUser->name }}</span>@endif
                    @if($item->duration_minutes)<span>{{ $item->duration_minutes }} Min.</span>@endif
                    @unless($guided || $isInfo)<span class="text-[color:var(--nx-faint)]">{{ $texts['unguided'] }}</span>@endunless
                    @if($item->linked_object_type === 'signals_signal')<span>aus Signal #{{ $item->linked_object_id }}@if($item->ownerAgent) · vorbereitet von {{ $item->ownerAgent->name }}@endif</span>@endif
                    @if($item->linked_object_type === 'signals_signal' && ! $item->local_solution_confirmed)<span class="text-[color:var(--nx-warning)]">4a noch nicht vom Bereich bestätigt</span>@endif
                </div>
            </button>
            @if($item->isProposalDraft())
                <x-nx-badge variant="warning" title="Ein Agent hat diesen Punkt aus einem Signal vorbereitet. Der Vorsitz entscheidet, ob er auf die Agenda kommt.">Entwurf</x-nx-badge>
                @if($canEdit)
                    <div class="flex shrink-0 items-center gap-1">
                        <x-nx-button variant="secondary" size="sm" wire:click="acceptProposal({{ $item->id }})">Übernehmen</x-nx-button>
                        <x-nx-button variant="ghost" size="sm" wire:click="rejectProposal({{ $item->id }})" wire:confirm="Entwurf herausnehmen? Das Signal geht zurück an den Bereich.">Herausnehmen</x-nx-button>
                    </div>
                @endif
            @endif
            @if($verdict)
                <x-nx-badge :variant="$verdict['variant']" title="{{ count($checked) }} von {{ $checkMax }} Kriterien">{{ count($checked) }}/{{ $checkMax }} · {{ $verdict['label'] }}</x-nx-badge>
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

        <div x-show="open" x-cloak class="space-y-6 border-t border-[color:var(--nx-line)] px-5 py-5">
            @if($item->description)
                <p class="m-0 max-w-3xl whitespace-pre-line text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $item->description }}</p>
            @endif

            @unless($isInfo)
                {{-- Leitfaden des Formats --}}
                <div class="grid grid-cols-1 gap-x-8 gap-y-5 md:grid-cols-2">
                    @foreach($fields as $field => $f)
                        <div class="space-y-1 {{ ! empty($f['aside']) ? 'md:border-l-2 md:border-[color:var(--nx-line)] md:pl-4' : '' }}">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">
                                <span class="tabular-nums">{{ $f['no'] }}</span> · {{ $f['label'] }}
                            </div>
                            @if(filled($item->{$field}))
                                <div class="whitespace-pre-line text-[15px] leading-relaxed text-[color:var(--nx-text)]">{{ $item->{$field} }}</div>
                            @else
                                <div class="text-sm text-[color:var(--nx-faint)]">noch offen</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @foreach($hints as $hint)
                    <x-nx-callout :variant="$hint['variant']" :icon="$hint['icon']">{{ $hint['text'] }}</x-nx-callout>
                @endforeach

                {{-- Nach der Sitzung: Erfolgskriterien des Formats --}}
                <div class="space-y-3 rounded-[8px] border border-[color:var(--nx-line)] p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-[color:var(--nx-text)]">{{ $outcome['title'] }}</div>
                            <div class="text-xs text-[color:var(--nx-muted)]">{{ $outcome['question'] }}</div>
                        </div>
                        @php $verdictNow = $format->verdict(count($checked), $checkMax); @endphp
                        <x-nx-badge :variant="count($checked) ? $verdictNow['variant'] : 'neutral'">
                            {{ count($checked) }} von {{ $checkMax }}{{ count($checked) ? ' · ' . $verdictNow['label'] : ' · noch nicht bewertet' }}
                        </x-nx-badge>
                    </div>
                    <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                        @foreach($checks as $checkValue => $checkLabel)
                            @php $on = in_array($checkValue, $checked, true); @endphp
                            <button type="button" @if($canEdit) wire:click="toggleCheck({{ $item->id }}, '{{ $checkValue }}')" @endif
                                    class="flex items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-left text-sm transition-colors {{ $canEdit ? 'hover:bg-[color:var(--nx-hover)]' : 'cursor-default' }} {{ $on ? 'text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)]' }}">
                                @svg($on ? 'heroicon-s-check-circle' : 'heroicon-o-stop', 'w-5 h-5 shrink-0 ' . ($on ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-faint)]'))
                                {{ $checkLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endunless

            @if($canEdit)
                <div class="flex gap-2">
                    <x-nx-button variant="ghost" size="sm" wire:click="openItem({{ $item->id }})">@svg('heroicon-o-pencil-square', 'w-4 h-4') {{ $texts['guide_button'] }}</x-nx-button>
                    <x-nx-button variant="ghost" size="sm" wire:click="openDecision(null, {{ $item->id }})">@svg('heroicon-o-clipboard-document-check', 'w-4 h-4') Beschluss zu diesem Punkt</x-nx-button>
                </div>
            @endif
        </div>
    </div>
</x-nx-card>
