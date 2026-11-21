{{-- Fokusräume: je Fokusraum eine Karte mit den drei Spalten Zielbilder / Hindernisse / Meilensteine. --}}
@if($focusAreas->count() > $recommendedMax)
    <x-nx-callout variant="warning" icon="heroicon-o-light-bulb" title="Fokussieren heißt verzichten">
        {{ $focusAreas->count() }} Fokusräume sind mehr als die empfohlenen 5–7. Wenige Themen mit voller Kraft tragen weiter als viele halbherzig verfolgte – was könnte bewusst wegfallen?
    </x-nx-callout>
@endif

@if($focusAreas->isEmpty())
    <x-nx-card>
        <x-nx-empty icon="heroicon-o-squares-2x2">
            <span class="block text-sm text-[color:var(--nx-muted)]">Noch keine Fokusräume.</span>
            <span class="mt-1 block max-w-md">Fokusplanung heißt verzichten: Wählt 5–7 Themen, die über das Geschäftsjahr hinaus tragen – und entscheidet bewusst, was wegfällt. Je Fokusraum beschreibt ihr Zielbilder, Hindernisse und Meilensteine.</span>
            @if($canEdit)
                <x-slot name="action"><x-nx-button variant="secondary" wire:click="openFocusArea">Ersten Fokusraum anlegen</x-nx-button></x-slot>
            @endif
        </x-nx-empty>
    </x-nx-card>
@else
    <div class="space-y-4">
        @foreach($focusAreas as $fa)
            <x-nx-card flush wire:key="fa-{{ $fa->id }}">
                {{-- Kopf --}}
                <div class="flex items-start justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-[6px] bg-[color:var(--nx-accent-soft)] text-xs font-semibold tabular-nums text-[color:var(--nx-muted)]">{{ $loop->iteration }}</span>
                            <h3 class="m-0 truncate text-base font-semibold text-[color:var(--nx-text)]">{{ $fa->title }}</h3>
                            @if($fa->forecast)
                                <x-nx-badge variant="neutral">@svg('heroicon-o-arrow-uturn-left', 'w-3 h-3') {{ $fa->forecast->title }}</x-nx-badge>
                            @endif
                        </div>
                        @if($fa->description)
                            <p class="mt-1 mb-0 text-sm text-[color:var(--nx-muted)] whitespace-pre-line">{{ $fa->description }}</p>
                        @endif
                    </div>
                    @if($canEdit)
                        <div class="flex shrink-0 items-center">
                            <x-nx-button variant="ghost" icon wire:click="moveFocusArea({{ $fa->id }}, -1)" title="Nach oben" :disabled="$loop->first">@svg('heroicon-o-chevron-up', 'w-4 h-4')</x-nx-button>
                            <x-nx-button variant="ghost" icon wire:click="moveFocusArea({{ $fa->id }}, 1)" title="Nach unten" :disabled="$loop->last">@svg('heroicon-o-chevron-down', 'w-4 h-4')</x-nx-button>
                            <x-nx-dropdown>
                                <x-nx-dropdown-item wire:click="openFocusArea({{ $fa->id }})">@svg('heroicon-o-pencil-square', 'w-4 h-4') Bearbeiten</x-nx-dropdown-item>
                                <x-nx-dropdown-divider />
                                <x-nx-dropdown-item variant="danger" wire:click="deleteFocusArea({{ $fa->id }})" wire:confirm="Fokusraum „{{ $fa->title }}“ löschen?">@svg('heroicon-o-trash', 'w-4 h-4') Löschen</x-nx-dropdown-item>
                            </x-nx-dropdown>
                        </div>
                    @endif
                </div>

                {{-- Drei Spalten --}}
                <div class="grid grid-cols-1 divide-y divide-[color:var(--nx-line)] lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                    @foreach($kinds as $kind => $cfg)
                        @php
                            $items = match ($kind) {
                                'vision_image' => $fa->visionImages,
                                'obstacle' => $fa->obstacles,
                                default => $fa->milestones,
                            };
                            $question = $fa->{$cfg['question']} ?: $cfg['default_question'];
                        @endphp
                        <div class="flex min-w-0 flex-col p-4">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-muted)]">
                                    @svg($cfg['icon'], 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $cfg['plural'] }}
                                    <span class="font-normal tabular-nums text-[color:var(--nx-faint)]">{{ $items->count() }}</span>
                                </span>
                                @if($canEdit)
                                    <button type="button" wire:click="openItem('{{ $kind }}', {{ $fa->id }})" title="{{ $cfg['label'] }} hinzufügen"
                                            class="rounded-[6px] p-1 text-[color:var(--nx-faint)] transition-colors hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]">
                                        @svg('heroicon-o-plus', 'w-4 h-4')
                                    </button>
                                @endif
                            </div>
                            <p class="mb-3 mt-0 text-xs italic text-[color:var(--nx-faint)]">{{ $question }}</p>

                            <ul class="m-0 list-none space-y-1 p-0">
                                @forelse($items as $item)
                                    @php $overdue = $kind === 'milestone' && isset($map['overdue'][$item->id]); @endphp
                                    <li wire:key="{{ $kind }}-{{ $item->id }}" class="group flex items-start gap-2 rounded-[6px] px-2 py-1.5 transition-colors hover:bg-[color:var(--nx-hover)]">
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm text-[color:var(--nx-text)] {{ $kind === 'milestone' && $item->status === \Platform\ManagementBook\Enums\MilestoneStatus::CANCELLED ? 'line-through text-[color:var(--nx-faint)]' : '' }}">
                                                @if($canEdit)
                                                    <button type="button" wire:click="openItem('{{ $kind }}', {{ $fa->id }}, {{ $item->id }})" class="text-left hover:underline">{{ $item->title }}</button>
                                                @else
                                                    {{ $item->title }}
                                                @endif
                                            </div>
                                            @if($kind === 'milestone')
                                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-[color:var(--nx-muted)]">
                                                    @if($canEdit)
                                                        <div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative inline-block">
                                                            <button type="button" @click="open = ! open" title="Status ändern" class="inline-flex items-center">
                                                                <x-nx-badge :variant="$milestoneVariant($item->status)" dot>{{ $item->status->label() }} @svg('heroicon-o-chevron-down', 'w-3 h-3 opacity-60')</x-nx-badge>
                                                            </button>
                                                            <div x-show="open" style="display:none" @click.outside="open = false" x-transition
                                                                 class="absolute left-0 z-50 mt-1 w-40 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-1 shadow-[var(--nx-shadow-pop)]">
                                                                @foreach($statusOptions as $opt)
                                                                    <x-nx-dropdown-item wire:click="setMilestoneStatus({{ $item->id }}, '{{ $opt['value'] }}')">
                                                                        <span class="w-3">@if($item->status->value === $opt['value'])@svg('heroicon-o-check', 'w-3 h-3')@endif</span>{{ $opt['label'] }}
                                                                    </x-nx-dropdown-item>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @else
                                                        <x-nx-badge :variant="$milestoneVariant($item->status)" dot>{{ $item->status->label() }}</x-nx-badge>
                                                    @endif
                                                    <span class="tabular-nums">
                                                        @if($item->target_year)
                                                            {{ $item->target_quarter ? 'Q' . $item->target_quarter . ' ' : '' }}{{ $item->target_year }}
                                                        @elseif($item->target_date)
                                                            {{ $item->target_date->format('d.m.Y') }}
                                                        @else
                                                            ohne Termin
                                                        @endif
                                                    </span>
                                                    @if($overdue)
                                                        <x-nx-badge variant="warning">@svg('heroicon-o-clock', 'w-3 h-3') überfällig</x-nx-badge>
                                                    @endif
                                                    @if($item->status === \Platform\ManagementBook\Enums\MilestoneStatus::REACHED && $item->reached_at)
                                                        <span>erreicht {{ $item->reached_at->format('d.m.Y') }}</span>
                                                    @endif
                                                </div>
                                            @elseif($item->description)
                                                <div class="mt-0.5 line-clamp-2 text-xs text-[color:var(--nx-muted)]">{{ $item->description }}</div>
                                            @endif
                                        </div>
                                        @if($canEdit)
                                            <div class="flex shrink-0 opacity-0 transition-opacity group-hover:opacity-100">
                                                <button type="button" wire:click.stop="moveItem('{{ $kind }}', {{ $item->id }}, -1)" title="Nach oben" class="rounded p-0.5 text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)]">@svg('heroicon-o-chevron-up', 'w-3.5 h-3.5')</button>
                                                <button type="button" wire:click.stop="moveItem('{{ $kind }}', {{ $item->id }}, 1)" title="Nach unten" class="rounded p-0.5 text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)]">@svg('heroicon-o-chevron-down', 'w-3.5 h-3.5')</button>
                                            </div>
                                        @endif
                                    </li>
                                @empty
                                    <li class="px-2 py-1.5 text-xs text-[color:var(--nx-faint)]">Noch keine {{ $cfg['plural'] }}.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endforeach
                </div>
            </x-nx-card>
        @endforeach
    </div>
@endif
