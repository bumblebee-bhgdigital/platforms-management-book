{{--
    Bereich „Strategie“: Fokusräume (Zielbilder / Hindernisse / Meilensteine), Transformation Map und Regnose.
    Logik in Livewire/Book/Strategy.php; Partials unter book/strategy/*.
--}}
@php
    $milestoneVariant = fn ($status) => match ($status) {
        \Platform\ManagementBook\Enums\MilestoneStatus::REACHED => 'success',
        \Platform\ManagementBook\Enums\MilestoneStatus::IN_PROGRESS => 'info',
        \Platform\ManagementBook\Enums\MilestoneStatus::MISSED => 'danger',
        default => 'neutral',
    };
    $sections = [
        'focus' => ['label' => 'Fokusräume', 'icon' => 'heroicon-o-squares-2x2', 'count' => $focusAreas->count()],
        'map' => ['label' => 'Transformation Map', 'icon' => 'heroicon-o-map', 'count' => $map['total']],
        'regnose' => ['label' => 'Regnose & Forecasts', 'icon' => 'heroicon-o-arrow-uturn-left', 'count' => $forecasts->count()],
    ];
@endphp
<div class="space-y-6">
    {{-- Kennzahlen --}}
    <x-nx-stat-grid :cols="4">
        <x-nx-stat label="Fokusräume" :value="$focusAreas->count()" icon="heroicon-o-squares-2x2"
                   :hint="$focusAreas->count() > $recommendedMax ? 'mehr als empfohlen (5–7)' : 'empfohlen 5–7'"
                   :accent="$focusAreas->count() > $recommendedMax ? 'var(--nx-warning)' : null" />
        <x-nx-stat label="Meilensteine erreicht" :value="$map['reached_percent'] . ' %'" icon="heroicon-o-check-circle"
                   :hint="$map['reached'] . ' von ' . $map['total'] . ' Waypoints'" accent="var(--nx-success)" />
        <x-nx-stat label="Überfällig" :value="count($map['overdue'])" icon="heroicon-o-clock"
                   hint="offen, Zielzeitraum vorbei" :accent="count($map['overdue']) ? 'var(--nx-danger)' : null" />
        <x-nx-stat label="Nächster Waypoint" :value="$map['next'] ? ($map['next']->target_year ? 'Q' . ($map['next']->target_quarter ?: '–') . ' ' . $map['next']->target_year : 'ohne Termin') : '–'"
                   icon="heroicon-o-flag" :hint="$map['next']?->title ?? 'kein offener Meilenstein'" />
    </x-nx-stat-grid>

    {{-- Unterbereiche --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-0.5">
            @foreach($sections as $key => $s)
                <button type="button" wire:click="setSection('{{ $key }}')" wire:key="strat-sec-{{ $key }}"
                        class="inline-flex items-center gap-1.5 rounded-[6px] px-3 py-1.5 text-sm transition-colors {{ $section === $key ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">
                    @svg($s['icon'], 'w-4 h-4')
                    {{ $s['label'] }}
                    <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $s['count'] }}</span>
                </button>
            @endforeach
        </div>
        @if($canEdit)
            @if($section === 'regnose')
                <x-nx-button variant="primary" wire:click="openForecast">@svg('heroicon-o-plus', 'w-4 h-4') Forecast</x-nx-button>
            @else
                <x-nx-button variant="primary" wire:click="openFocusArea">@svg('heroicon-o-plus', 'w-4 h-4') Fokusraum</x-nx-button>
            @endif
        @endif
    </div>

    @switch($section)
        @case('map')
            @include('management-book::livewire.book.strategy.map')
            @break
        @case('regnose')
            @include('management-book::livewire.book.strategy.regnose')
            @break
        @default
            @include('management-book::livewire.book.strategy.focus-areas')
    @endswitch

    @include('management-book::livewire.book.strategy.modals')
</div>
