{{-- Regnose & Forecasts: Zukunftsbilder mit Zieljahr, aktuelle Version, Historie, zugeordnete Fokusräume. --}}

@if($forecasts->isEmpty())
    <x-nx-card>
        <x-nx-empty icon="heroicon-o-arrow-uturn-left">
            <span class="block text-base font-semibold text-[color:var(--nx-text)]">Noch keine Regnose</span>
            <span class="mx-auto mt-2 block max-w-lg text-sm leading-relaxed text-[color:var(--nx-muted)]">Wie sieht es 2030 aus, wenn alles gelungen ist? Beschreibe einen beobachtbaren Zustand, keine Floskel wie „Marktführer werden“.</span>
            <span class="mx-auto mt-3 block max-w-lg rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-4 py-3 text-left text-sm leading-relaxed text-[color:var(--nx-muted)]">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Beispiel</span>
                „2030 buchen Unternehmen im Rheinland ihre Events zuerst bei uns. Unsere drei Locations sind an 200 Tagen ausgelastet, das Eventteam plant eigenständig, und Stammkunden kommen ohne Akquise wieder.“
            </span>
            @if($canEdit)
                <x-slot name="action"><x-nx-button variant="secondary" wire:click="openForecast">Erste Regnose anlegen</x-nx-button></x-slot>
            @endif
        </x-nx-empty>
    </x-nx-card>
@else
    <div class="space-y-4">
        @foreach($forecasts as $forecast)
            <x-nx-card flush wire:key="fc-{{ $forecast->id }}">
                <div class="flex items-start justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="m-0 text-base font-semibold text-[color:var(--nx-text)]">{{ $forecast->title }}</h3>
                            @if($forecast->target_date)
                                <x-nx-badge variant="info">Zieljahr {{ $forecast->target_date->year }}</x-nx-badge>
                            @endif
                            <x-nx-badge>{{ $forecast->currentVersion ? 'Version ' . $forecast->currentVersion->version : 'noch keine Version' }}</x-nx-badge>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-[color:var(--nx-muted)]">
                            @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5 text-[color:var(--nx-faint)]')
                            @forelse($forecast->focusAreas as $fa)
                                <span>{{ $fa->title }}</span>@if(! $loop->last)<span class="text-[color:var(--nx-faint)]">·</span>@endif
                            @empty
                                <span>Kein Fokusraum zugeordnet</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        @if($canEdit)
                            <x-nx-button variant="secondary" wire:click="openVersion({{ $forecast->id }})">@svg('heroicon-o-document-plus', 'w-4 h-4') Neue Version</x-nx-button>
                            <x-nx-dropdown>
                                <x-nx-dropdown-item wire:click="openForecast({{ $forecast->id }})">@svg('heroicon-o-pencil-square', 'w-4 h-4') Titel & Zieljahr</x-nx-dropdown-item>
                                <x-nx-dropdown-divider />
                                <x-nx-dropdown-item variant="danger" wire:click="deleteForecast({{ $forecast->id }})" wire:confirm="Forecast „{{ $forecast->title }}“ löschen? Zugeordnete Fokusräume bleiben erhalten.">@svg('heroicon-o-trash', 'w-4 h-4') Löschen</x-nx-dropdown-item>
                            </x-nx-dropdown>
                        @endif
                    </div>
                </div>

                <div class="px-4 py-4">
                    @if(filled($forecast->content))
                        <x-nx-prose class="text-sm">{!! \Illuminate\Support\Str::markdown((string) $forecast->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</x-nx-prose>
                    @else
                        <p class="m-0 text-sm text-[color:var(--nx-faint)]">Noch kein Inhalt. „Neue Version“ beschreibt das Zukunftsbild.</p>
                    @endif
                </div>

                @if($forecast->versions->isNotEmpty())
                    <div class="border-t border-[color:var(--nx-line)]">
                        <button type="button" wire:click="toggleHistory({{ $forecast->id }})" class="flex w-full items-center gap-1.5 px-4 py-2 text-left text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">
                            @svg($historyForecastId === $forecast->id ? 'heroicon-o-chevron-down' : 'heroicon-o-chevron-right', 'w-3.5 h-3.5')
                            Versionshistorie ({{ $forecast->versions->count() }})
                        </button>
                        @if($historyForecastId === $forecast->id)
                            <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] border-t border-[color:var(--nx-line)] p-0">
                                @foreach($forecast->versions as $v)
                                    <li class="px-4 py-3" wire:key="fcv-{{ $v->id }}">
                                        <div class="mb-1 flex flex-wrap items-center gap-2 text-xs text-[color:var(--nx-muted)]">
                                            <x-nx-badge :variant="$v->id === $forecast->current_version_id ? 'success' : 'neutral'">Version {{ $v->version }}</x-nx-badge>
                                            <span class="tabular-nums">{{ $v->created_at?->format('d.m.Y H:i') }}</span>
                                            @if($v->change_note)<span>· {{ $v->change_note }}</span>@endif
                                        </div>
                                        <div class="line-clamp-3 whitespace-pre-line text-xs text-[color:var(--nx-muted)]">{{ $v->content }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </x-nx-card>
        @endforeach
    </div>
@endif
