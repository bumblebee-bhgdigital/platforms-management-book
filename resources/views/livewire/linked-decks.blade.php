{{--
    Präsentationen (Slides-Decks) an einer Sitzung oder einem Gremium. Nur sichtbar, wenn das Slides-Modul installiert ist.
    Live: wire:poll prüft die Deck-Revisionen; schreibt ein Agent Folien, aktualisiert sich die Vorschau.
--}}
<div @if($available && $decks->isNotEmpty()) wire:poll.5s @endif>
    @if($available)
        <section class="space-y-4" data-revisions="{{ $revisionKey }}">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="m-0 {{ $compact ? 'text-sm' : 'text-base' }} font-semibold text-[color:var(--nx-text)]">
                        Präsentationen <span class="font-normal text-[color:var(--nx-faint)]">{{ $decks->count() }}</span>
                    </h2>
                    @unless($compact)
                        <p class="mt-1 mb-0 max-w-[70ch] text-sm leading-relaxed text-[color:var(--nx-muted)]">
                            Folien hängen direkt an der Sitzung. Agenten bereiten sie über die <code>slides.*</code>-Tools vor und können sie
                            bis in die Sitzung hinein ergänzen – die Vorschau und der Präsentationsmodus aktualisieren sich live.
                        </p>
                    @endunless
                </div>
                @if($canEdit)
                    <div class="flex shrink-0 gap-2">
                        <x-nx-button variant="ghost" size="sm" wire:click="openLink">@svg('heroicon-o-link', 'w-4 h-4') Verknüpfen</x-nx-button>
                        <x-nx-button variant="secondary" size="sm" wire:click="openCreate">@svg('heroicon-o-plus', 'w-4 h-4') Präsentation</x-nx-button>
                    </div>
                @endif
            </div>

            @if($decks->isEmpty())
                <div class="rounded-[8px] border border-dashed border-[color:var(--nx-line-strong)] px-5 py-6 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                    @if($compact)
                        Noch keine Präsentation am Gremium – etwa eine Vorlage, die jede Sitzung wiederverwendet.
                    @else
                        Noch keine Präsentation. Lege eine an oder bitte einen Agenten, die Sitzung vorzubereiten:
                        „Erstelle für diese Sitzung ein Deck mit je einer Folie pro Agenda-Punkt.“
                    @endif
                </div>
            @else
                <div class="grid gap-4 {{ $compact ? 'sm:grid-cols-2' : 'md:grid-cols-2' }}">
                    @foreach($decks as $deck)
                        @php $first = $deck->slides->first(); @endphp
                        <div wire:key="deck-{{ $deck->id }}-{{ $deck->revision }}" class="overflow-hidden rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)]">
                            <div class="border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)]">
                                @if($first)
                                    @include('slides::livewire.partials.slide-frame', [
                                        'doc' => \Platform\Slides\Rendering\SlideDocument::render($deck, $first),
                                        'size' => $deck->size,
                                        'label' => $deck->title . ' – Folie 1',
                                    ])
                                @else
                                    <div class="flex aspect-video items-center justify-center text-sm text-[color:var(--nx-faint)]">Noch keine Folien</div>
                                @endif
                            </div>
                            <div class="space-y-3 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $deck->title }}</div>
                                        <div class="text-xs text-[color:var(--nx-faint)]">
                                            {{ $deck->slides_count }} {{ $deck->slides_count === 1 ? 'Folie' : 'Folien' }} · aktualisiert {{ $deck->updated_at?->diffForHumans() }}
                                        </div>
                                    </div>
                                    <x-nx-badge :variant="$deck->status->badgeVariant()" dot>{{ $deck->status->label() }}</x-nx-badge>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($routes['present'] && $deck->slides_count > 0)
                                        <x-nx-button variant="primary" size="sm" :href="route('slides.decks.present', $deck)" target="_blank">@svg('heroicon-o-play', 'w-4 h-4') Präsentieren</x-nx-button>
                                    @endif
                                    @if($routes['presenter'] && $deck->slides_count > 0)
                                        <x-nx-button variant="secondary" size="sm" :href="route('slides.decks.presenter', $deck)" target="_blank">@svg('heroicon-o-presentation-chart-line', 'w-4 h-4') Referentenansicht</x-nx-button>
                                    @endif
                                    @if($routes['show'])
                                        <x-nx-button variant="ghost" size="sm" :href="route('slides.decks.show', $deck)">Öffnen</x-nx-button>
                                    @endif
                                    @if($routes['pdf'] && $deck->slides_count > 0)
                                        <x-nx-button variant="ghost" size="sm" :href="route('slides.decks.pdf', $deck)" target="_blank">PDF</x-nx-button>
                                    @endif
                                    @if($canEdit)
                                        <x-nx-button variant="ghost" size="sm" icon class="ml-auto" wire:click="unlinkDeck({{ $deck->id }})"
                                                     wire:confirm="Verknüpfung lösen? Das Deck selbst bleibt erhalten." title="Verknüpfung lösen">@svg('heroicon-o-link-slash', 'w-4 h-4')</x-nx-button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Anlegen --}}
        <x-nx-modal wire:model="showCreate" size="sm">
            <x-slot name="header">Präsentation anlegen</x-slot>
            <div class="space-y-3">
                <x-nx-input-text name="newTitle" label="Titel" wire:model="newTitle" required />
                <p class="m-0 text-xs leading-relaxed text-[color:var(--nx-muted)]">Das Deck startet leer mit dem BHG-Theme und hängt direkt hier. Folien schreibst du im Slides-Modul – oder ein Agent über die slides.*-Tools.</p>
            </div>
            <x-slot name="footer">
                <x-nx-button variant="ghost" wire:click="$set('showCreate', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="createDeck">Anlegen</x-nx-button>
            </x-slot>
        </x-nx-modal>

        {{-- Vorhandenes Deck verknüpfen --}}
        <x-nx-modal wire:model="showLink" size="md">
            <x-slot name="header">Vorhandenes Deck verknüpfen</x-slot>
            <div class="space-y-3">
                <x-nx-input-text name="linkSearch" label="Suche" placeholder="Titel …" wire:model.live.debounce.300ms="linkSearch" />
                <div class="divide-y divide-[color:var(--nx-line)] rounded-[8px] border border-[color:var(--nx-line)]">
                    @forelse($candidates as $c)
                        <button type="button" wire:key="cand-{{ $c->id }}" wire:click="linkDeck({{ $c->id }})"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-[color:var(--nx-hover)]">
                            @svg('heroicon-o-presentation-chart-bar', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                            <span class="min-w-0 flex-1 truncate">{{ $c->title }}</span>
                            <span class="text-xs text-[color:var(--nx-faint)]">{{ $c->updated_at?->format('d.m.Y') }}</span>
                        </button>
                    @empty
                        <p class="m-0 px-4 py-4 text-sm text-[color:var(--nx-muted)]">Keine passenden Decks im Team.</p>
                    @endforelse
                </div>
            </div>
            <x-slot name="footer">
                <x-nx-button variant="ghost" wire:click="$set('showLink', false)">Schließen</x-nx-button>
            </x-slot>
        </x-nx-modal>
    @endif
</div>
