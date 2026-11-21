{{--
    Book-Übersicht: Titelblatt (Vision/Mission), Regelkreis als Kreis, Kennzahlen mit Einordnung,
    Risikoprofil, nächste Sitzung, was klemmt. Nur Hauptspalte – die Orientierungsspalte liefert die Shell.
    Daten: Platform\ManagementBook\Services\BookInsights::forBook(). Navigation in andere Tabs über $parent.setTab().
--}}
@php
    $cycle = $i['cycle'];
    $current = $cycle[$station] ?? $cycle[0];
    $levels = collect(\Platform\ManagementBook\Enums\RiskLevel::cases());
    $plain = fn ($md, $len) => \Illuminate\Support\Str::limit(trim(strip_tags(\Illuminate\Support\Str::markdown((string) $md))), $len);
    $vision = $i['vision'] && filled($i['vision']->content) ? $plain($i['vision']->content, 320) : null;
    $mission = $i['mission'] && filled($i['mission']->content) ? $plain($i['mission']->content, 320) : null;
    $stateColor = [
        'done' => 'var(--nx-success)',
        'active' => 'var(--nx-info)',
        'attention' => 'var(--nx-warning)',
        'open' => 'var(--nx-muted)',
        'untracked' => 'var(--nx-faint)',
    ];
    $eyebrow = collect([
        $nodeNames ? implode(', ', $nodeNames) : 'Noch keinem Knoten zugeordnet',
        $book->horizon_year ? 'Unterwegs nach ' . $book->horizon_year : null,
    ])->filter()->implode(' · ');
@endphp
<div class="space-y-12">

    {{-- Titelblatt --}}
    <section class="border-b border-[color:var(--nx-line)] pb-10 pt-2">
        <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">{{ $eyebrow }}</div>
        @if($vision || $mission)
            <div class="mt-4 max-w-[60ch] space-y-5">
                @if($vision)
                    <div>
                        <div class="mb-2 inline-flex items-center gap-1.5 text-xs font-medium text-[color:var(--nx-muted)]">@svg('heroicon-o-eye', 'w-3.5 h-3.5') Vision – wohin wir wollen</div>
                        <p class="m-0 text-2xl font-semibold leading-tight tracking-tight text-[color:var(--nx-text)] md:text-3xl">{{ $vision }}</p>
                    </div>
                @endif
                @if($mission)
                    <div>
                        <div class="mb-1.5 inline-flex items-center gap-1.5 text-xs font-medium text-[color:var(--nx-muted)]">@svg('heroicon-o-heart', 'w-3.5 h-3.5') Mission – warum es uns gibt</div>
                        <p class="m-0 {{ $vision ? 'text-base leading-relaxed text-[color:var(--nx-muted)]' : 'text-2xl font-semibold leading-tight tracking-tight text-[color:var(--nx-text)] md:text-3xl' }}">{{ $mission }}</p>
                    </div>
                @endif
                @if(! $vision || ! $mission)
                    <p class="m-0 text-sm text-[color:var(--nx-muted)]">
                        {{ $vision ? 'Die Mission' : 'Die Vision' }} fehlt noch – erst beide zusammen geben Richtung und Grund.
                        <button type="button" wire:click="$parent.setTab('book')" class="text-[color:var(--nx-text)] underline">Im Book ergänzen</button>
                    </p>
                @endif
            </div>
            <button type="button" wire:click="$parent.setTab('book')"
                    class="mt-6 inline-flex items-center gap-1.5 text-sm text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">
                Das ganze Book lesen · {{ $i['chapters']['filled'] }} von {{ $i['chapters']['total'] }} Kapiteln geschrieben @svg('heroicon-o-arrow-right', 'w-4 h-4')
            </button>
        @else
            <div class="mt-4 max-w-[60ch]">
                <p class="m-0 text-2xl font-semibold leading-tight tracking-tight text-[color:var(--nx-text)] md:text-3xl">Dieses Book wartet auf seine Richtung.</p>
                <p class="mt-3 mb-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                    Ein Book beginnt mit Herkunft, Standort und Richtung: Woher kommen wir, wo stehen wir, wohin wollen wir?
                    Mission und Vision sind der Kern – alles Weitere, von den Fokusräumen bis zum Lenkungsausschuss, richtet sich daran aus.
                </p>
                <p class="mt-2 mb-0 text-sm italic text-[color:var(--nx-faint)]">Beispiel Vision: „2030 sind wir die erste Adresse für Events im Rheinland.“</p>
                <div class="mt-5">
                    <x-nx-button variant="primary" size="md" wire:click="$parent.setTab('book')">
                        @svg('heroicon-o-pencil-square', 'w-4 h-4') Mission und Vision schreiben
                    </x-nx-button>
                </div>
            </div>
        @endif
    </section>

    {{-- Regelkreis --}}
    <section class="space-y-4">
        <div>
            <h3 class="m-0 flex items-center gap-2 text-base font-semibold text-[color:var(--nx-text)]">@svg('heroicon-o-arrow-path', 'w-4 h-4 text-[color:var(--nx-faint)]') Regelkreis</h3>
            <p class="mt-1 mb-0 max-w-[70ch] text-sm leading-relaxed text-[color:var(--nx-muted)]">
                Strategie wird nicht verkündet, sondern in der Organisation anschlussfähig gemacht. Acht Stationen greifen ineinander –
                der Zustand jeder Station ergibt sich aus den erfassten Daten. Wähle eine Station, um zu sehen, worum es geht und was vorliegt.
            </p>
        </div>

        <div class="grid grid-cols-1 items-center gap-8 2xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            {{-- Kreis (ab md) --}}
            <div class="hidden md:block">
                <svg viewBox="-70 20 740 560" class="block h-auto w-full max-w-[680px]" role="img" aria-label="Regelkreis mit acht Stationen">
                    <defs>
                        <marker id="mb-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                            <path d="M 0 0 L 10 5 L 0 10 z" style="fill: var(--nx-line-strong);" />
                        </marker>
                    </defs>
                    <circle cx="{{ $ring['cx'] }}" cy="{{ $ring['cy'] }}" r="{{ $ring['r'] }}" fill="none" style="stroke: var(--nx-line);" stroke-width="1" />
                    @foreach($ring['arcs'] as $arc)
                        <path d="{{ $arc }}" fill="none" style="stroke: var(--nx-line-strong);" stroke-width="1.5" marker-end="url(#mb-arrow)" />
                    @endforeach

                    {{-- Mitte: ausgewählte Station --}}
                    <text x="{{ $ring['cx'] }}" y="{{ $ring['cy'] - 22 }}" text-anchor="middle" style="fill: var(--nx-faint); font-size: 14px; letter-spacing: .08em;">STATION {{ $station + 1 }} / 8</text>
                    @foreach(explode("\n", wordwrap($current['name'], 18, "\n", true)) as $li => $line)
                        <text x="{{ $ring['cx'] }}" y="{{ $ring['cy'] + 4 + $li * 28 }}" text-anchor="middle" style="fill: var(--nx-text); font-size: 24px; font-weight: 600;">{{ $line }}</text>
                    @endforeach
                    <text x="{{ $ring['cx'] }}" y="{{ $ring['cy'] + 36 + (count(explode("\n", wordwrap($current['name'], 18, "\n", true))) - 1) * 22 }}" text-anchor="middle"
                          style="fill: {{ $stateColor[$current['state']] }}; font-size: 16px; font-weight: 500;">{{ $states[$current['state']]['label'] }}</text>

                    {{-- Stationen --}}
                    @foreach($ring['nodes'] as $idx => $node)
                        @php $s = $cycle[$idx]; $sel = $idx === $station; $col = $stateColor[$s['state']]; @endphp
                        <g wire:key="ring-{{ $s['key'] }}" wire:click="selectStation({{ $idx }})" style="cursor: pointer;" role="button" aria-label="Station {{ $idx + 1 }}: {{ $s['name'] }} ({{ $states[$s['state']]['label'] }})">
                            <title>{{ $idx + 1 }} · {{ $s['name'] }} – {{ $states[$s['state']]['label'] }}</title>
                            <circle cx="{{ $node['x'] }}" cy="{{ $node['y'] }}" r="{{ $node['r'] + 10 }}" fill="transparent" />
                            <circle cx="{{ $node['x'] }}" cy="{{ $node['y'] }}" r="{{ $node['r'] }}"
                                    style="fill: {{ $sel ? 'var(--nx-accent)' : 'var(--nx-surface)' }}; stroke: {{ $sel ? 'var(--nx-accent)' : $col }};"
                                    stroke-width="{{ $s['state'] === 'untracked' ? 1.5 : 2.5 }}" @if($s['state'] === 'untracked' && ! $sel) stroke-dasharray="4 3" @endif />
                            <text x="{{ $node['x'] }}" y="{{ $node['y'] + 5 }}" text-anchor="middle"
                                  style="fill: {{ $sel ? 'var(--nx-on-accent)' : 'var(--nx-text)' }}; font-size: 18px; font-weight: 600;">{{ $idx + 1 }}</text>
                            @if(in_array($s['state'], ['done', 'attention'], true))
                                <circle cx="{{ $node['x'] + 17 }}" cy="{{ $node['y'] - 17 }}" r="6" style="fill: {{ $col }}; stroke: var(--nx-surface);" stroke-width="2" />
                            @endif
                            @foreach($node['lines'] as $li => $line)
                                <text x="{{ $node['lx'] }}" y="{{ $node['ly'] + $li * $node['lineH'] }}" text-anchor="{{ $node['anchor'] }}"
                                      style="fill: {{ $sel ? 'var(--nx-text)' : 'var(--nx-muted)' }}; font-size: 19px; font-weight: {{ $sel ? 600 : 500 }};">{{ $line }}</text>
                            @endforeach
                        </g>
                    @endforeach
                </svg>
            </div>

            {{-- Liste (unter md) --}}
            <ol class="m-0 list-none space-y-1 p-0 md:hidden">
                @foreach($cycle as $idx => $s)
                    <li wire:key="station-list-{{ $s['key'] }}">
                        <button type="button" wire:click="selectStation({{ $idx }})"
                                class="flex w-full items-center gap-3 rounded-[6px] px-2 py-2 text-left text-sm transition-colors hover:bg-[color:var(--nx-hover)] {{ $idx === $station ? 'bg-[color:var(--nx-hover)]' : '' }}">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums {{ $idx === $station ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'border text-[color:var(--nx-text)]' }}"
                                  style="{{ $idx === $station ? '' : 'border-color: ' . $stateColor[$s['state']] }}">{{ $idx + 1 }}</span>
                            <span class="flex-1 text-[color:var(--nx-text)]">{{ $s['name'] }}</span>
                            <span class="text-xs" style="color: {{ $stateColor[$s['state']] }};">{{ $states[$s['state']]['label'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>

            {{-- Detail der ausgewählten Station --}}
            <div class="space-y-4">
                <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Station {{ $station + 1 }} · {{ $current['name'] }}</div>
                <p class="m-0 text-xl font-semibold leading-snug tracking-tight text-[color:var(--nx-text)]">„{{ $current['question'] }}“</p>
                <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $current['description'] }}</p>
                <div class="flex items-start gap-2 rounded-[8px] border border-[color:var(--nx-line)] px-3 py-2.5 text-sm">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" style="background: {{ $stateColor[$current['state']] }};"></span>
                    <span class="min-w-0">
                        <span class="font-medium text-[color:var(--nx-text)]">{{ $states[$current['state']]['label'] }}:</span>
                        <span class="text-[color:var(--nx-muted)]">{{ $current['evidence'] }}</span>
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($current['tab'])
                        <x-nx-button variant="secondary" size="sm" wire:click="$parent.setTab('{{ $current['tab'] }}')">
                            Zum Bereich @svg('heroicon-o-arrow-right', 'w-4 h-4')
                        </x-nx-button>
                    @endif
                    <x-nx-button variant="ghost" size="sm" wire:click="selectStation({{ ($station + 1) % 8 }})">
                        Nächste Station @svg('heroicon-o-arrow-path', 'w-4 h-4')
                    </x-nx-button>
                </div>
                <p class="m-0 text-xs leading-relaxed text-[color:var(--nx-faint)]">
                    Nach Station 8 beginnt der Kreis von vorn – mit mehr Wissen. „Nicht erfasst“ heißt: Dazu liegen keine Daten vor, nicht, dass es nicht stattfindet.
                </p>
            </div>
        </div>
    </section>

    {{-- Kennzahlen mit Einordnung --}}
    @php
        $kpis = [
            ['tab' => 'book', 'icon' => 'heroicon-o-book-open', 'label' => 'Kapitel geschrieben', 'value' => $i['chapters']['filled'] . ' / ' . $i['chapters']['total'],
             'text' => 'Wie vollständig das Book die Orientierung beschreibt – von der Herkunft bis zur Zusammenarbeit.', 'accent' => null],
            ['tab' => 'strategy', 'icon' => 'heroicon-o-viewfinder-circle', 'label' => 'Fokusräume', 'value' => $i['focus_areas'],
             'text' => $i['focus_areas'] > 7 ? 'Mehr als sieben – fokussieren heißt verzichten. Was kann wegfallen?' : 'Die wenigen Themen, die den Weg zur Vision tragen. Empfehlung: fünf bis sieben.',
             'accent' => $i['focus_areas'] > 7 ? 'var(--nx-warning)' : null],
            ['tab' => 'strategy', 'icon' => 'heroicon-o-flag', 'label' => 'Meilensteine erreicht', 'value' => $i['milestones']['reached_percent'] . ' %',
             'text' => $i['milestones']['reached'] . ' von ' . $i['milestones']['total'] . ' Waypoints erreicht' . ($i['milestones']['overdue']->isNotEmpty() ? ', ' . $i['milestones']['overdue']->count() . ' überfällig.' : '. Fortschritt auf der Transformation Map.'),
             'accent' => $i['milestones']['overdue']->isNotEmpty() ? 'var(--nx-warning)' : null],
            ['tab' => 'risks', 'icon' => 'heroicon-o-exclamation-triangle', 'label' => 'Kritische / hohe Risiken', 'value' => $i['risks']['by_level']['critical'] . ' / ' . $i['risks']['by_level']['high'],
             'text' => 'Von ' . $i['risks']['open'] . ' offenen Risiken. Was rot ist, gehört auf die Agenda des Lenkungsausschusses.',
             'accent' => $i['risks']['by_level']['critical'] > 0 ? 'var(--nx-danger)' : null],
            ['tab' => 'committees', 'icon' => 'heroicon-o-check-badge', 'label' => 'Offene Beschlüsse', 'value' => $i['open_decisions'],
             'text' => $i['overdue_decisions']->count() ? $i['overdue_decisions']->count() . ' überfällig – Anlass zu fragen, was die Umsetzung hindert.' : 'Beschlüsse aus den Gremien, die noch umgesetzt werden. Keiner überfällig.',
             'accent' => $i['overdue_decisions']->count() ? 'var(--nx-warning)' : null],
        ];
    @endphp
    <section class="space-y-4">
        <div>
            <h3 class="m-0 flex items-center gap-2 text-base font-semibold text-[color:var(--nx-text)]">@svg('heroicon-o-chart-bar', 'w-4 h-4 text-[color:var(--nx-faint)]') Lage in Zahlen</h3>
            <p class="mt-1 mb-0 max-w-[70ch] text-sm leading-relaxed text-[color:var(--nx-muted)]">Zahlen sind Steuerungssignal, kein Zeugnis. Sie zeigen, wo es sich lohnt, genauer hinzuschauen – ein Klick führt in den Bereich.</p>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach($kpis as $k)
                <button type="button" wire:click="$parent.setTab('{{ $k['tab'] }}')"
                        class="flex h-full flex-col gap-2 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-4 text-left transition-colors hover:bg-[color:var(--nx-hover)]">
                    <span class="flex items-center justify-between gap-2 text-xs font-medium text-[color:var(--nx-muted)]">
                        {{ $k['label'] }}
                        @if($k['accent'])
                            @svg($k['icon'], 'w-4 h-4 shrink-0', ['style' => 'color:' . $k['accent']])
                        @else
                            @svg($k['icon'], 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                        @endif
                    </span>
                    <span class="text-3xl font-semibold tabular-nums tracking-tight text-[color:var(--nx-text)]">{{ $k['value'] }}</span>
                    <span class="text-xs leading-relaxed text-[color:var(--nx-muted)]">{{ $k['text'] }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 gap-10 xl:grid-cols-2">
        {{-- Risikoprofil --}}
        <section class="space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="m-0 flex items-center gap-2 text-base font-semibold text-[color:var(--nx-text)]">@svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-[color:var(--nx-faint)]') Risikoprofil</h3>
                    <p class="mt-1 mb-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">Wahrscheinlichkeit × Auswirkung, über alle offenen Risiken. Je weiter oben rechts, desto dringender.</p>
                </div>
                <x-nx-button variant="ghost" size="sm" wire:click="$parent.setTab('risks')">Zur Matrix</x-nx-button>
            </div>
            <div class="flex flex-wrap items-start gap-6 rounded-[8px] border border-[color:var(--nx-line)] p-4">
                @include('management-book::livewire.partials.risk-matrix', ['counts' => $i['risks']['matrix'], 'compact' => true])
                <div class="min-w-[12rem] flex-1 space-y-2 text-sm">
                    @foreach($levels->reverse() as $level)
                        <div class="flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-2 text-[color:var(--nx-muted)]">
                                <span class="h-2.5 w-2.5 rounded-[3px]" style="background: {{ $level->tint() }};"></span>{{ $level->label() }}
                                <span class="text-xs text-[color:var(--nx-faint)]">· {{ $level->guidance() }}</span>
                            </span>
                            <span class="font-medium tabular-nums text-[color:var(--nx-text)]">{{ $i['risks']['by_level'][$level->value] }}</span>
                        </div>
                    @endforeach
                    @if($i['risks']['unrated'])
                        <div class="flex items-center justify-between gap-2 text-[color:var(--nx-faint)]">
                            <span>Unbewertet</span><span class="tabular-nums">{{ $i['risks']['unrated'] }}</span>
                        </div>
                    @endif
                    <div class="border-t border-[color:var(--nx-line)] pt-2 text-xs text-[color:var(--nx-muted)]">
                        Risikobereitschaft: <span class="text-[color:var(--nx-text)]">{{ $book->risk_appetite?->label() ?? 'noch nicht festgelegt' }}</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Nächste Sitzung --}}
        <section class="space-y-4">
            <div>
                <h3 class="m-0 flex items-center gap-2 text-base font-semibold text-[color:var(--nx-text)]">@svg('heroicon-o-calendar', 'w-4 h-4 text-[color:var(--nx-faint)]') Nächste Sitzung</h3>
                <p class="mt-1 mb-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">Im Lenkungsausschuss zeigt sich, welche Annahmen stimmen – er ist der Sensor des Regelkreises.</p>
            </div>
            @php $nm = $i['next_meeting']; @endphp
            @if($nm)
                <a href="{{ route('management-book.meetings.show', $nm) }}" wire:navigate
                   class="group flex items-center gap-4 rounded-[8px] border border-[color:var(--nx-line)] p-4 transition-colors hover:bg-[color:var(--nx-hover)]">
                    <span class="flex w-14 shrink-0 flex-col items-center rounded-[8px] border border-[color:var(--nx-line)] py-1.5">
                        <span class="text-[11px] font-medium uppercase text-[color:var(--nx-faint)]">{{ $nm->scheduled_at->translatedFormat('M') }}</span>
                        <span class="text-xl font-semibold tabular-nums leading-none text-[color:var(--nx-text)]">{{ $nm->scheduled_at->format('d') }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $nm->title }}</span>
                        <span class="block truncate text-xs text-[color:var(--nx-muted)]">
                            {{ $nm->committee?->name ?? 'Gremium' }} · {{ $nm->scheduled_at->translatedFormat('D, H:i') }} Uhr{{ $nm->location ? ' · ' . $nm->location : '' }}
                        </span>
                    </span>
                    <x-nx-badge variant="info">in {{ (int) ceil(now()->floatDiffInDays($nm->scheduled_at)) }} Tg.</x-nx-badge>
                    @svg('heroicon-o-chevron-right', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                </a>
                <p class="m-0 text-xs text-[color:var(--nx-faint)]">{{ $i['meetings_last_90'] }} Sitzung(en) in den letzten 90 Tagen durchgeführt.</p>
            @else
                <div class="rounded-[8px] border border-dashed border-[color:var(--nx-line-strong)] p-5 text-sm">
                    <p class="m-0 text-[color:var(--nx-text)]">Keine Sitzung geplant.</p>
                    <p class="mt-1 mb-0 leading-relaxed text-[color:var(--nx-muted)]">Ohne regelmäßigen Abgleich fehlt die Rückkopplung. Ein monatlicher Lenkungsausschuss ist ein guter Start.</p>
                    <div class="mt-3"><x-nx-button variant="secondary" size="sm" wire:click="$parent.setTab('committees')">Zu den Gremien</x-nx-button></div>
                </div>
            @endif
        </section>
    </div>

    {{-- Was klemmt --}}
    @php $stuck = $i['milestones']['overdue']->count() + $i['overdue_decisions']->count() + ($i['review_due'] ? 1 : 0); @endphp
    <section class="space-y-4">
        <div>
            <h3 class="m-0 flex items-center gap-2 text-base font-semibold text-[color:var(--nx-text)]">
                @svg('heroicon-o-bolt', 'w-4 h-4 text-[color:var(--nx-faint)]') Was gerade klemmt
                @if($stuck)<span class="text-sm font-normal text-[color:var(--nx-faint)]">{{ $stuck }}</span>@endif
            </h3>
            <p class="mt-1 mb-0 max-w-[70ch] text-sm leading-relaxed text-[color:var(--nx-muted)]">Überfälliges ist kein Versagen, sondern ein Anlass zu fragen: Was hindert – und wobei braucht es Hilfe des Gesamtsystems?</p>
        </div>
        @if($stuck === 0)
            <div class="flex items-center gap-2 rounded-[8px] border border-[color:var(--nx-line)] px-4 py-3 text-sm text-[color:var(--nx-muted)]">
                @svg('heroicon-o-check-circle', 'w-4 h-4 text-[color:var(--nx-success)]') Nichts überfällig – Meilensteine, Beschlüsse und Review sind im Plan.
            </div>
        @else
            <x-nx-card flush>
                <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                    @if($i['review_due'])
                        <li><x-nx-list-item icon="heroicon-o-arrow-path" title="Review des Books fällig" :subtitle="'seit ' . $book->next_review_at->format('d.m.Y') . ' – Zeit für die Rückkopplung'" :href="route('management-book.books.show', $book) . '?tab=book'">
                            <x-slot name="trailing"><x-nx-badge variant="warning">Rückkopplung</x-nx-badge></x-slot>
                        </x-nx-list-item></li>
                    @endif
                    @foreach($i['milestones']['overdue']->take(5) as $m)
                        <li wire:key="od-ms-{{ $m->id }}">
                            <x-nx-list-item icon="heroicon-o-flag" :title="$m->title" :subtitle="$m->focusArea?->title" :meta="\Platform\ManagementBook\Services\BookInsights::milestoneWhen($m)" :href="route('management-book.books.show', $book) . '?tab=strategy&view=map'">
                                <x-slot name="trailing"><x-nx-badge variant="warning">Meilenstein</x-nx-badge></x-slot>
                            </x-nx-list-item>
                        </li>
                    @endforeach
                    @foreach($i['overdue_decisions']->take(5) as $d)
                        <li wire:key="od-dec-{{ $d->id }}">
                            <x-nx-list-item icon="heroicon-o-check-badge" :title="$d->text"
                                            :subtitle="$d->ownerUser?->name ?? $d->ownerAgent?->name ?? 'ohne Verantwortliche:n'"
                                            :meta="'fällig ' . $d->due_date->format('d.m.Y')"
                                            :href="route('management-book.meetings.show', $d->meeting_id)">
                                <x-slot name="trailing"><x-nx-badge variant="warning">Beschluss</x-nx-badge></x-slot>
                            </x-nx-list-item>
                        </li>
                    @endforeach
                </ul>
            </x-nx-card>
        @endif
    </section>
</div>
