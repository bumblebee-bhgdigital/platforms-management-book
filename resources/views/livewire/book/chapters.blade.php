{{--
    Das Book als Dokument: Kapitel in Lesereihenfolge (StrategicDocumentType::bookOrder()), je Kapitel Leitfrage,
    Markdown-Inhalt, Version. Bearbeiten legt eine neue Version an; der Verlauf bleibt einsehbar.
--}}
<div class="grid grid-cols-1 gap-10 lg:grid-cols-[13rem_minmax(0,1fr)]">

    {{-- Inhaltsverzeichnis --}}
    <aside class="lg:sticky lg:top-4 lg:self-start">
        <div class="mb-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Inhalt</div>
        <div class="mb-4">
            <div class="mb-1 flex items-center justify-between text-xs text-[color:var(--nx-muted)]">
                <span>Kapitel geschrieben</span>
                <span class="tabular-nums">{{ $filled }} / {{ $chapters->count() }}</span>
            </div>
            <div class="h-1 overflow-hidden rounded-full bg-[color:var(--nx-accent-soft)]">
                <div class="h-full rounded-full bg-[color:var(--nx-accent)]" style="width: {{ $chapters->count() ? round($filled / $chapters->count() * 100) : 0 }}%"></div>
            </div>
        </div>
        <nav class="space-y-0.5">
            @foreach($chapters as $n => $c)
                <a href="#chapter-{{ $c['type']->value }}"
                   class="flex items-center gap-2 rounded-[6px] px-2 py-1.5 text-sm text-[color:var(--nx-muted)] no-underline transition-colors hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]">
                    <span class="w-4 shrink-0 text-right text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $n + 1 }}</span>
                    <span class="truncate">{{ $c['type']->label() }}</span>
                    @if(filled($c['doc']?->content))
                        @svg('heroicon-s-check-circle', 'ml-auto w-3.5 h-3.5 shrink-0 text-[color:var(--nx-success)]')
                    @else
                        <span class="ml-auto h-3.5 w-3.5 shrink-0 rounded-full border border-dashed border-[color:var(--nx-line-strong)]"></span>
                    @endif
                </a>
            @endforeach
        </nav>
    </aside>

    {{-- Dokument --}}
    <article class="min-w-0">
        <header class="max-w-[70ch] border-b border-[color:var(--nx-line)] pb-8">
            <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Management Book{{ $book->horizon_year ? ' · Horizont ' . $book->horizon_year : '' }}</div>
            <h2 class="mt-2 mb-0 text-3xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $book->title }}</h2>
            <p class="mt-3 mb-0 text-[15px] leading-7 text-[color:var(--nx-muted)]">
                Dieses Book verdichtet, wohin wir wollen und nach welcher Logik wir handeln. Es ist eine Momentaufnahme:
                stabil genug für Orientierung, veränderbar genug für Lernen. Jede Änderung wird als neue Version festgehalten.
            </p>
        </header>

        @foreach($chapters as $n => $c)
            @php $t = $c['type']; $doc = $c['doc']; @endphp
            <section id="chapter-{{ $t->value }}" wire:key="chapter-{{ $t->value }}" class="max-w-[70ch] scroll-mt-6 border-b border-[color:var(--nx-line)] py-10 last:border-0">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">
                            @svg($t->icon(), 'w-3.5 h-3.5') Kapitel {{ $n + 1 }} · {{ $t->question() }}
                        </div>
                        <h3 class="mt-2 mb-0 text-xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $doc?->title ?: $t->label() }}</h3>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        @if($doc)
                            <x-nx-button variant="ghost" size="sm" wire:click="history('{{ $t->value }}')" title="Versionen">
                                @svg('heroicon-o-clock', 'w-4 h-4') v{{ $doc->version }}
                            </x-nx-button>
                        @endif
                        @if($canEdit && filled($doc?->content))
                            <x-nx-button variant="ghost" size="sm" icon wire:click="edit('{{ $t->value }}')" title="Bearbeiten">
                                @svg('heroicon-o-pencil-square', 'w-4 h-4')
                            </x-nx-button>
                        @endif
                    </div>
                </div>

                <div class="mt-4">
                    @if(filled($doc?->content))
                        <x-nx-prose class="text-[15px] leading-7">{!! \Illuminate\Support\Str::markdown($doc->content, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</x-nx-prose>
                        <p class="mt-4 mb-0 text-xs text-[color:var(--nx-faint)]">
                            Version {{ $doc->version }} · gültig seit {{ $doc->valid_from?->format('d.m.Y') ?? '–' }}
                            @if($doc->created_by && isset($authors[$doc->created_by])) · {{ $authors[$doc->created_by] }} @endif
                            @if($doc->change_note) · „{{ $doc->change_note }}“ @endif
                        </p>
                    @else
                        <div class="rounded-[8px] border border-dashed border-[color:var(--nx-line-strong)] px-5 py-5">
                            <p class="m-0 text-[15px] leading-7 text-[color:var(--nx-muted)]">{{ $hints[$t->value] ?? $t->question() }}</p>
                            @if($canEdit)
                                <div class="mt-3">
                                    <x-nx-button variant="secondary" size="sm" wire:click="edit('{{ $t->value }}')">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4') Kapitel schreiben
                                    </x-nx-button>
                                </div>
                            @else
                                <p class="mt-2 mb-0 text-xs text-[color:var(--nx-faint)]">Noch nicht geschrieben.</p>
                            @endif
                        </div>
                    @endif
                </div>
            </section>
        @endforeach
    </article>

    {{-- Bearbeiten --}}
    <x-nx-modal wire:model="showEdit" size="lg">
        <x-slot name="header">
            <div class="text-base font-semibold">{{ $editLabel?->label() }}</div>
            <div class="text-sm italic text-[color:var(--nx-muted)]">{{ $editLabel?->question() }}</div>
        </x-slot>
        <div class="space-y-4">
            @if($editLabel && isset($hints[$editLabel->value]))
                <p class="m-0 rounded-[6px] bg-[color:var(--nx-accent-soft)] px-3 py-2 text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $hints[$editLabel->value] }}</p>
            @endif
            <x-nx-input-text name="form.title" label="Überschrift" wire:model="form.title" required />
            <x-nx-input-textarea name="form.content" label="Inhalt" hint="Markdown" wire:model="form.content" rows="14" class="font-mono text-[13px]" />
            <x-nx-input-text name="form.change_note" label="Änderungsnotiz" hint="optional" placeholder="Warum ändert sich das Kapitel?" wire:model="form.change_note" />
            <p class="m-0 text-xs text-[color:var(--nx-muted)]">Speichern legt eine neue Version an. Die bisherige bleibt im Verlauf erhalten.</p>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showEdit', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="save">Neue Version speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Verlauf --}}
    <x-nx-modal wire:model="showHistory" size="lg" hideFooter>
        <x-slot name="header">
            <div class="text-base font-semibold">Verlauf · {{ $historyLabel?->label() }}</div>
        </x-slot>
        <ol class="m-0 list-none space-y-5 p-0">
            @forelse($versions as $v)
                <li wire:key="ver-{{ $v->id }}" class="border-l-2 pl-4 {{ $v->is_active ? 'border-[color:var(--nx-accent)]' : 'border-[color:var(--nx-line)]' }}">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-[color:var(--nx-muted)]">
                        <span class="font-semibold text-[color:var(--nx-text)]">Version {{ $v->version }}</span>
                        @if($v->is_active)<x-nx-badge variant="success">aktiv</x-nx-badge>@endif
                        <span>seit {{ $v->valid_from?->format('d.m.Y') ?? '–' }}</span>
                        @if($v->created_by && isset($authors[$v->created_by]))<span>· {{ $authors[$v->created_by] }}</span>@endif
                    </div>
                    @if($v->change_note)
                        <p class="mt-1 mb-0 text-sm italic text-[color:var(--nx-muted)]">„{{ $v->change_note }}“</p>
                    @endif
                    <x-nx-prose class="mt-2 text-sm">{!! \Illuminate\Support\Str::markdown((string) $v->content, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</x-nx-prose>
                </li>
            @empty
                <li class="text-sm text-[color:var(--nx-muted)]">Keine Versionen.</li>
            @endforelse
        </ol>
    </x-nx-modal>
</div>
