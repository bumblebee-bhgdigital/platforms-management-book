{{--
    5×5-Risikomatrix (Heatmap): Zeilen = Eintrittswahrscheinlichkeit E (5 oben → 1 unten), Spalten = Auswirkung A (1 → 5).
    Jede Zelle ist nach der Ampel ihres Scores (E × A) getönt und zeigt die Anzahl der Risiken.

    @include('management-book::livewire.partials.risk-matrix', [
        'counts'    => [p => [i => n]],      // Anzahl je Feld (fehlende = 0)
        'compact'   => false,                // true: kleine Variante ohne Achsentexte (Übersicht)
        'clickable' => false,                // true: Zellen rufen wire:click="filterCell(p, i)" auf
        'selected'  => null,                 // [p, i] hervorgehobene Zelle
    ])
--}}
@php
    $counts = $counts ?? [];
    $compact = $compact ?? false;
    $clickable = $clickable ?? false;
    $selected = $selected ?? null;
    $pLabels = \Platform\ManagementBook\Enums\RiskLevel::probabilityLabels();
    $iLabels = \Platform\ManagementBook\Enums\RiskLevel::impactLabels();
    $cell = $compact ? 'h-7 min-w-7 text-xs' : 'h-12 min-w-12 text-sm';
@endphp
<div class="inline-block">
    <div class="flex">
        @unless($compact)
            <div class="flex w-6 items-center justify-center">
                <span class="-rotate-90 whitespace-nowrap text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Wahrscheinlichkeit</span>
            </div>
        @endunless
        <div>
            <div class="grid gap-1" style="grid-template-columns: {{ $compact ? '' : '1.5rem ' }}repeat(5, minmax(0, 1fr));">
                @for($p = 5; $p >= 1; $p--)
                    @unless($compact)
                        <div class="flex items-center justify-end pr-1 text-xs tabular-nums text-[color:var(--nx-muted)]" title="{{ $pLabels[$p] }}">{{ $p }}</div>
                    @endunless
                    @for($i = 1; $i <= 5; $i++)
                        @php
                            $level = \Platform\ManagementBook\Enums\RiskLevel::fromScore($p * $i);
                            $n = (int) ($counts[$p][$i] ?? 0);
                            $isSel = $selected && (int) $selected[0] === $p && (int) $selected[1] === $i;
                        @endphp
                        <{{ $clickable ? 'button type=button' : 'div' }}
                            @if($clickable) wire:click="filterCell({{ $p }}, {{ $i }})" @endif
                            title="E {{ $p }} ({{ $pLabels[$p] }}) × A {{ $i }} ({{ $iLabels[$i] }}) = {{ $p * $i }} · {{ $level->label() }}"
                            class="{{ $cell }} flex items-center justify-center rounded-[6px] font-semibold tabular-nums transition-[box-shadow] {{ $clickable ? 'cursor-pointer hover:ring-2 hover:ring-[color:var(--nx-line-strong)]' : '' }} {{ $isSel ? 'ring-2 ring-[color:var(--nx-accent)]' : '' }}"
                            style="background: {{ $level->tint() }}; color: {{ $n > 0 ? 'var(--nx-text)' : 'transparent' }};">
                            {{ $n > 0 ? $n : '·' }}
                        </{{ $clickable ? 'button' : 'div' }}>
                    @endfor
                @endfor
                @unless($compact)
                    <div></div>
                    @for($i = 1; $i <= 5; $i++)
                        <div class="pt-1 text-center text-xs tabular-nums text-[color:var(--nx-muted)]" title="{{ $iLabels[$i] }}">{{ $i }}</div>
                    @endfor
                @endunless
            </div>
            @unless($compact)
                <div class="mt-1 pl-6 text-center text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Auswirkung</div>
            @endunless
        </div>
    </div>
</div>
