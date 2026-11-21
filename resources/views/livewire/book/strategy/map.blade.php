{{--
    Transformation Map: Spalten = Jahre × Quartale, Zeilen = Fokusraum-Bahnen, Meilensteine als Waypoints.
    Meilensteine nur mit Jahr landen im Q4 des Jahres (Ende des Zielzeitraums, wie die Überfälligkeit).
--}}
@php
    $mStyle = function ($m) use ($map) {
        $overdue = isset($map['overdue'][$m->id]);
        return match (true) {
            $m->status === \Platform\ManagementBook\Enums\MilestoneStatus::REACHED => ['dot' => 'var(--nx-success)', 'bg' => 'rgba(47,158,68,.10)', 'border' => 'rgba(47,158,68,.35)'],
            $m->status === \Platform\ManagementBook\Enums\MilestoneStatus::MISSED => ['dot' => 'var(--nx-danger)', 'bg' => 'rgba(224,49,49,.08)', 'border' => 'rgba(224,49,49,.35)'],
            $m->status === \Platform\ManagementBook\Enums\MilestoneStatus::CANCELLED => ['dot' => 'var(--nx-faint)', 'bg' => 'transparent', 'border' => 'var(--nx-line)'],
            $overdue => ['dot' => 'var(--nx-warning)', 'bg' => 'rgba(232,89,12,.08)', 'border' => 'rgba(232,89,12,.45)'],
            $m->status === \Platform\ManagementBook\Enums\MilestoneStatus::IN_PROGRESS => ['dot' => 'var(--nx-info)', 'bg' => 'rgba(25,113,194,.08)', 'border' => 'rgba(25,113,194,.35)'],
            default => ['dot' => 'var(--nx-muted)', 'bg' => 'var(--nx-surface)', 'border' => 'var(--nx-line-strong)'],
        };
    };
    $cols = count($map['years']) * 4 + ($map['has_undated'] ? 1 : 0);
@endphp

@if($focusAreas->isEmpty() || $map['total'] === 0)
    <x-nx-card>
        <x-nx-empty icon="heroicon-o-map">
            <span class="block text-sm text-[color:var(--nx-muted)]">Die Transformation Map ist noch leer.</span>
            <span class="mt-1 block max-w-md">Sie entsteht aus den Meilensteinen der Fokusräume: Jeder Meilenstein mit Zieljahr und Quartal wird zum Waypoint auf dem Weg vom Zukunftsbild zurück in die Gegenwart.</span>
            @if($canEdit)
                <x-slot name="action"><x-nx-button variant="secondary" wire:click="setSection('focus')">Zu den Fokusräumen</x-nx-button></x-slot>
            @endif
        </x-nx-empty>
    </x-nx-card>
@else
    <x-nx-card flush>
        {{-- Fortschritt --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 border-b border-[color:var(--nx-line)] px-4 py-3 text-sm">
            <div class="flex min-w-[12rem] flex-1 items-center gap-3">
                <span class="text-[color:var(--nx-muted)]">Fortschritt</span>
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-[color:var(--nx-accent-soft)]">
                    <div class="h-full rounded-full bg-[color:var(--nx-success)]" style="width: {{ $map['reached_percent'] }}%"></div>
                </div>
                <span class="tabular-nums font-medium text-[color:var(--nx-text)]">{{ $map['reached_percent'] }} %</span>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs text-[color:var(--nx-muted)]">
                @foreach([['Geplant', 'var(--nx-muted)'], ['In Arbeit', 'var(--nx-info)'], ['Erreicht', 'var(--nx-success)'], ['Überfällig', 'var(--nx-warning)'], ['Verfehlt', 'var(--nx-danger)']] as [$lbl, $c])
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full" style="background: {{ $c }}"></span>{{ $lbl }}</span>
                @endforeach
            </div>
        </div>

        @if($map['next'])
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm text-[color:var(--nx-muted)]">
                @svg('heroicon-o-arrow-right-circle', 'w-4 h-4 text-[color:var(--nx-info)]')
                Nächster Waypoint: <span class="font-medium text-[color:var(--nx-text)]">{{ $map['next']->title }}</span>
                <span class="tabular-nums">{{ $map['next']->target_year ? ($map['next']->target_quarter ? 'Q' . $map['next']->target_quarter . ' ' : '') . $map['next']->target_year : 'ohne Termin' }}</span>
            </div>
        @endif

        {{-- Raster --}}
        <div class="overflow-x-auto">
            <div class="grid min-w-max text-xs" style="grid-template-columns: 13rem repeat({{ $cols }}, minmax(7.5rem, 1fr));">
                {{-- Kopf: Jahre --}}
                <div class="sticky left-0 z-10 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)]"></div>
                @foreach($map['years'] as $year)
                    <div class="border-b border-l border-[color:var(--nx-line)] px-2 pt-2 pb-1 text-sm font-semibold tabular-nums {{ $year === $map['current']['year'] ? 'text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)]' }}" style="grid-column: span 4 / span 4;">
                        {{ $year }}
                    </div>
                @endforeach
                @if($map['has_undated'])
                    <div class="border-b border-l border-[color:var(--nx-line)] px-2 pt-2 pb-1 text-sm font-semibold text-[color:var(--nx-muted)]">Ohne Termin</div>
                @endif

                {{-- Kopf: Quartale --}}
                <div class="sticky left-0 z-10 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-4 py-1 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Fokusraum</div>
                @foreach($map['years'] as $year)
                    @for($q = 1; $q <= 4; $q++)
                        @php $isNow = $year === $map['current']['year'] && $q === $map['current']['quarter']; @endphp
                        <div class="border-b border-[color:var(--nx-line)] px-2 py-1 text-[11px] tabular-nums {{ $q === 1 ? 'border-l' : '' }} {{ $isNow ? 'bg-[color:var(--nx-hover)] font-semibold text-[color:var(--nx-text)]' : 'text-[color:var(--nx-faint)]' }}">
                            Q{{ $q }}@if($isNow) · heute @endif
                        </div>
                    @endfor
                @endforeach
                @if($map['has_undated'])
                    <div class="border-b border-l border-[color:var(--nx-line)]"></div>
                @endif

                {{-- Bahnen --}}
                @foreach($focusAreas as $fa)
                    <div class="sticky left-0 z-10 flex items-start gap-2 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-4 py-3" wire:key="lane-{{ $fa->id }}">
                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-[5px] bg-[color:var(--nx-accent-soft)] text-[11px] font-semibold tabular-nums text-[color:var(--nx-muted)]">{{ $loop->iteration }}</span>
                        <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $fa->title }}</span>
                    </div>
                    @foreach($map['years'] as $year)
                        @for($q = 1; $q <= 4; $q++)
                            @php $isNow = $year === $map['current']['year'] && $q === $map['current']['quarter']; @endphp
                            <div class="space-y-1 border-b border-[color:var(--nx-line)] p-1.5 {{ $q === 1 ? 'border-l' : '' }} {{ $isNow ? 'bg-[color:var(--nx-hover)]' : '' }}">
                                @foreach($map['cells'][$fa->id][$year][$q] ?? [] as $m)
                                    @include('management-book::livewire.book.strategy.waypoint', ['m' => $m, 'fa' => $fa])
                                @endforeach
                            </div>
                        @endfor
                    @endforeach
                    @if($map['has_undated'])
                        <div class="space-y-1 border-b border-l border-[color:var(--nx-line)] p-1.5">
                            @foreach($map['undated'][$fa->id] ?? [] as $m)
                                @include('management-book::livewire.book.strategy.waypoint', ['m' => $m, 'fa' => $fa])
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </x-nx-card>
    <p class="m-0 text-xs text-[color:var(--nx-faint)]">Meilensteine nur mit Zieljahr stehen im Q4 des Jahres. Überfällig heißt: geplant oder in Arbeit, aber der Zielzeitraum ist vorbei.</p>
@endif
