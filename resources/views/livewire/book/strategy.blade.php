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
    // Einstieg je Unterbereich: was es ist, wozu, wie es gelingt
    $intros = [
        'focus' => [
            'title' => 'Wenige Themen, die wirklich tragen',
            'text' => 'Ein Fokusraum bündelt ein Thema, das über das laufende Geschäftsjahr hinaus wichtig ist. Je Raum beschreibt ihr, woran man den Erfolg erkennt (Zielbilder), was heute im Weg steht (Hindernisse) und welche Etappen dorthin führen (Meilensteine).',
            'tip' => 'Gut gemacht: 5–7 Räume, jedes Zielbild beobachtbar formuliert, jeder Meilenstein mit Quartal. Und bewusst entscheiden, was nicht Fokus ist.',
        ],
        'map' => [
            'title' => 'Der Weg über die Zeit',
            'text' => 'Die Transformation Map legt alle Meilensteine auf eine Zeitachse – eine Bahn je Fokusraum. So wird sichtbar, ob der Weg realistisch verteilt ist, wo sich Etappen ballen und was überfällig ist.',
            'tip' => 'Gut gemacht: Jeder Waypoint hat ein Quartal. Überfällige Meilensteine nicht verstecken, sondern im Lenkungsausschuss besprechen: Was hindert uns?',
        ],
        'regnose' => [
            'title' => 'Aus der Zukunft zurückdenken',
            'text' => 'Eine Regnose beschreibt den Zustand im Zieljahr so konkret, dass man ihn sehen könnte – und denkt von dort rückwärts: Welche Fähigkeiten, Strukturen und Entscheidungen braucht es heute? Fokusräume können einer Regnose zugeordnet werden.',
            'tip' => 'Gut gemacht: keine Floskel wie „Marktführer werden“, sondern ein Bild, das sich beobachten lässt. Jede Überarbeitung wird als neue Version festgehalten.',
        ],
    ];
    $intro = $intros[$section] ?? $intros['focus'];
@endphp
<div class="space-y-8">
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

    {{-- Einstieg in den Unterbereich --}}
    <div class="grid gap-4 rounded-[10px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-5 py-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
        <div>
            <div class="text-base font-semibold text-[color:var(--nx-text)]">{{ $intro['title'] }}</div>
            <p class="mt-1 mb-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $intro['text'] }}</p>
        </div>
        <div class="flex gap-2 text-sm leading-relaxed text-[color:var(--nx-muted)] lg:border-l lg:border-[color:var(--nx-line)] lg:pl-4">
            @svg('heroicon-o-light-bulb', 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
            <span>{{ $intro['tip'] }}</span>
        </div>
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
