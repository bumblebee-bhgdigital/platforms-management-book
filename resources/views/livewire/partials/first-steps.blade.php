{{--
    „Erste Schritte“ eines Books (Rail der Book-Detailseite). Reine Anzeige aus BookInsights::firstSteps().
    Offene Schritte springen per setTab() der Shell in den passenden Bereich.

    @include('management-book::livewire.partials.first-steps', ['steps' => $firstSteps, 'bookId' => $book->id])
--}}
@php
    $total = count($steps);
    $done = collect($steps)->where('done', true)->count();
    $percent = $total > 0 ? (int) round($done / $total * 100) : 0;
    $complete = $done === $total;
@endphp
<section class="space-y-3 rounded-[10px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-4" aria-label="Erste Schritte">
    <div class="flex items-baseline justify-between gap-2">
        <h3 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Erste Schritte</h3>
        <span class="text-xs tabular-nums text-[color:var(--nx-muted)]">{{ $done }} von {{ $total }}</span>
    </div>
    <div class="h-1.5 overflow-hidden rounded-full bg-[color:var(--nx-hover)]">
        <div class="h-full rounded-full transition-[width]" style="width: {{ $percent }}%; background: {{ $complete ? 'var(--nx-success)' : 'var(--nx-accent)' }};"></div>
    </div>

    @if($complete)
        <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
            Das Book ist arbeitsfähig. Ab jetzt zählt der Regelkreis: umsetzen, im Lenkungsausschuss beobachten, lernen und zurückspielen.
        </p>
    @else
        <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">Was ein Book braucht, damit es Orientierung gibt. Der nächste offene Schritt ist hervorgehoben.</p>
        @php $nextKey = collect($steps)->firstWhere('done', false)['key'] ?? null; @endphp
        <ol class="m-0 list-none space-y-0.5 p-0">
            @foreach($steps as $step)
                <li wire:key="step-{{ $step['key'] }}">
                    @if($step['done'])
                        <div class="flex items-start gap-2.5 rounded-[6px] px-2 py-1.5">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full" style="background: var(--nx-success);">
                                @svg('heroicon-s-check', 'w-3 h-3 text-[color:var(--nx-on-accent)]')
                            </span>
                            <span class="text-sm text-[color:var(--nx-muted)] line-through decoration-[color:var(--nx-line-strong)]">{{ $step['label'] }}</span>
                        </div>
                    @else
                        <button type="button" @if($step['key'] === 'node' && isset($bookId)) wire:click="startLinking({{ $bookId }})" @else wire:click="setTab('{{ $step['tab'] }}')" @endif
                                class="group flex w-full items-start gap-2.5 rounded-[6px] px-2 py-1.5 text-left transition-colors hover:bg-[color:var(--nx-hover)] {{ $step['key'] === $nextKey ? 'bg-[color:var(--nx-hover)]' : '' }}">
                            <span class="mt-0.5 h-4 w-4 shrink-0 rounded-full border {{ $step['key'] === $nextKey ? 'border-[color:var(--nx-accent)]' : 'border-[color:var(--nx-line-strong)]' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-[color:var(--nx-text)]">{{ $step['label'] }}</span>
                                @if($step['key'] === $nextKey)
                                    <span class="mt-0.5 block text-xs leading-relaxed text-[color:var(--nx-muted)]">{{ $step['hint'] }}</span>
                                @endif
                            </span>
                            @svg('heroicon-o-arrow-right', 'mt-0.5 w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)] opacity-0 transition-opacity group-hover:opacity-100')
                        </button>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</section>
