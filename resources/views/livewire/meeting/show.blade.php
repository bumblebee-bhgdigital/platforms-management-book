{{--
    Sitzungsseite (nx). Felder, Texte, Erfolgsmessung, Ablauf und Seitenspalte kommen aus dem Format des
    Gremientyps ($format, src/Formats): LA = vier Fragen + Handlungsfähigkeit, Strategiemeeting = Standort + Richtung,
    Board = Bericht + Freigabe. Breiter Kopf mit Ablauf-Hinweis je Status, Hauptspalte (Agenda, Beschlüsse, Protokoll),
    Seitenspalte (Teilnehmer, Unterlagen, Haltung/Rolle, Orientierung).
--}}
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$meeting->title" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="array_values(array_filter([
            ['label' => 'Management Books', 'href' => route('management-book.dashboard'), 'icon' => 'book-open'],
            $book ? ['label' => $book->title, 'href' => route('management-book.books.show', $book) . '?tab=committees'] : null,
            $committee ? ['label' => $committee->name, 'href' => route('management-book.committees.show', $committee)] : null,
            ['label' => $meeting->title],
        ]))">
            @if($canEdit)
                <x-nx-dropdown label="Aktionen">
                    <x-nx-dropdown-item wire:click="openMeetingEdit">@svg('heroicon-o-pencil-square', 'w-4 h-4') Bearbeiten</x-nx-dropdown-item>
                    <x-nx-dropdown-item wire:click="openItem">@svg('heroicon-o-plus', 'w-4 h-4') Agenda-Punkt</x-nx-dropdown-item>
                    <x-nx-dropdown-item wire:click="openDecision">@svg('heroicon-o-clipboard-document-check', 'w-4 h-4') Beschluss</x-nx-dropdown-item>
                    <x-nx-dropdown-divider />
                    <x-nx-dropdown-item variant="danger" wire:click="deleteMeeting" wire:confirm="Sitzung löschen? (Soft-Delete)">@svg('heroicon-o-trash', 'w-4 h-4') Löschen</x-nx-dropdown-item>
                </x-nx-dropdown>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="full" spacing="space-y-8">
        @php
            $openDec = $decisions->where('status.value', 'open');
            $overdueDec = $openDec->filter(fn ($d) => $d->due_date && $d->due_date->isPast())->count();
            $present = $meeting->attendees->where('attended', true)->count();
            $planned = $items->sum('duration_minutes');
            $statusValue = $meeting->status?->value;
            $flow = $format->flow($statusValue);
            $outcome = $format->outcome();
            $texts = $format->texts();
            $stance = $format->stance();
        @endphp
        <div class="mx-auto w-full max-w-[1440px] space-y-8">
            {{-- Kopf --}}
            <div class="space-y-3">
                <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Sitzung{{ $committee ? ' · ' . $committee->name : '' }} · Format {{ $format->name() }}</div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="m-0 text-3xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $meeting->title }}</h1>
                    <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Committee\Show::meetingBadge($meeting->status)" dot>{{ $meeting->status?->label() }}</x-nx-badge>
                </div>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-[color:var(--nx-muted)]">
                    <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-calendar-days', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $meeting->scheduled_at?->translatedFormat('l, d.m.Y · H:i') ?? 'kein Termin' }}</span>
                    @if($meeting->duration_minutes)<span class="inline-flex items-center gap-1.5">@svg('heroicon-o-clock', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $meeting->duration_minutes }} Min.</span>@endif
                    @if($meeting->location)<span class="inline-flex items-center gap-1.5">@svg('heroicon-o-map-pin', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $meeting->location }}</span>@endif
                </div>
                @if($canEdit)
                    <div class="flex flex-wrap items-center gap-1 pt-1">
                        <span class="mr-1 text-xs text-[color:var(--nx-faint)]">Status:</span>
                        @foreach($statusOptions as $s)
                            <button type="button" wire:click="setStatus('{{ $s->value }}')"
                                    class="rounded-full px-3 py-1 text-xs transition-colors {{ $meeting->status === $s ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">
                                {{ $s->label() }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($flow)
                <div class="flex items-start gap-3 rounded-[8px] border border-[color:var(--nx-line)] px-5 py-4">
                    @svg($flow['icon'], 'mt-0.5 w-5 h-5 shrink-0 text-[color:var(--nx-muted)]')
                    <div class="space-y-0.5">
                        <div class="text-sm font-semibold text-[color:var(--nx-text)]">Jetzt: {{ $flow['title'] }}</div>
                        <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $flow['text'] }}</p>
                    </div>
                </div>
            @endif

            <div class="grid gap-10 xl:grid-cols-[minmax(0,1fr)_300px]">
                {{-- Hauptspalte --}}
                <div class="min-w-0 space-y-10">
                    <x-nx-stat-grid :cols="4">
                        <x-nx-stat label="Agenda" :value="$items->count() . ' Punkte'" icon="heroicon-o-list-bullet"
                                   :hint="($capability['guided'] ? $capability['guided'] . ' eingeordnet' : 'noch ohne Einordnung') . ($planned ? ' · ' . $planned . ' Min.' : '')" />
                        <x-nx-stat :label="$outcome['stat']" :value="$capability['pct'] !== null ? $capability['pct'] . ' %' : '–'" icon="heroicon-o-bolt"
                                   :hint="$capability['max'] ? $capability['done'] . ' von ' . $capability['max'] . ' Kriterien erfüllt' : $outcome['pending']"
                                   :accent="$capability['pct'] === null ? null : ($capability['pct'] >= 80 ? 'var(--nx-success)' : ($capability['pct'] >= 50 ? 'var(--nx-warning)' : 'var(--nx-danger)'))" />
                        <x-nx-stat label="Offene Beschlüsse" :value="$openDec->count()" icon="heroicon-o-clipboard-document-check"
                                   :hint="$overdueDec ? $overdueDec . ' überfällig' : $decisions->count() . ' insgesamt'" :accent="$overdueDec ? 'var(--nx-danger)' : null" />
                        <x-nx-stat label="Teilnehmer" :value="$present . ' / ' . $meeting->attendees->count()" icon="heroicon-o-users" hint="anwesend / eingeladen" />
                    </x-nx-stat-grid>

                    {{-- Präsentationen (Slides, lose gekoppelt) --}}
                    @if(\Platform\ManagementBook\Livewire\LinkedDecks::available())
                        <livewire:management-book.linked-decks :linkable-type="\Platform\ManagementBook\Models\ManagementBookMeeting::MORPH_ALIAS" :linkable-id="$meeting->id" :key="'decks-meeting-' . $meeting->id" />
                    @endif

                    {{-- Agenda --}}
                    <section class="space-y-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="max-w-2xl space-y-1">
                                <h2 class="m-0 text-base font-semibold text-[color:var(--nx-text)]">Agenda <span class="font-normal text-[color:var(--nx-faint)]">{{ $items->count() }}</span></h2>
                                <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $texts['agenda_intro'] }}</p>
                            </div>
                            @if($canEdit)
                                <x-nx-button size="sm" wire:click="openItem">@svg('heroicon-o-plus', 'w-4 h-4') Punkt</x-nx-button>
                            @endif
                        </div>

                        <div class="space-y-4">
                            @forelse($items as $idx => $item)
                                @include('management-book::livewire.meeting.partials.agenda-item', ['item' => $item, 'idx' => $idx, 'last' => $loop->last])
                            @empty
                                <x-nx-card>
                                    <div class="space-y-3 p-2">
                                        <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                                            Noch keine Agenda. Ein guter Punkt klingt zum Beispiel so: <em class="text-[color:var(--nx-text)]">{{ $texts['empty_agenda'] }}</em>
                                        </p>
                                        @if($canEdit)
                                            <x-nx-button size="sm" wire:click="openItem">Ersten Punkt anlegen</x-nx-button>
                                        @endif
                                    </div>
                                </x-nx-card>
                            @endforelse
                        </div>
                    </section>

                    {{-- Beschlüsse --}}
                    <section class="space-y-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="max-w-2xl space-y-1">
                                <h2 class="m-0 text-base font-semibold text-[color:var(--nx-text)]">Beschlüsse <span class="font-normal text-[color:var(--nx-faint)]">{{ $decisions->count() }}</span></h2>
                                <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $texts['decision_intro'] }}</p>
                            </div>
                            @if($canEdit)
                                <x-nx-button variant="secondary" size="sm" wire:click="openDecision">@svg('heroicon-o-plus', 'w-4 h-4') Beschluss</x-nx-button>
                            @endif
                        </div>
                        <x-nx-card flush>
                            @forelse($decisions as $d)
                                @php $od = $d->status?->value === 'open' && $d->due_date && $d->due_date->isPast(); @endphp
                                <div class="group flex items-start gap-3 border-b border-[color:var(--nx-line)] px-5 py-4 last:border-b-0" wire:key="decision-{{ $d->id }}">
                                    @if($canEdit)
                                        <button type="button" title="{{ $d->status?->value === 'implemented' ? 'Wieder öffnen' : 'Als umgesetzt markieren' }}"
                                                wire:click="setDecisionStatus({{ $d->id }}, '{{ $d->status?->value === 'implemented' ? 'open' : 'implemented' }}')"
                                                class="mt-0.5 shrink-0 {{ $d->status?->value === 'implemented' ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)]' }}">
                                            @svg($d->status?->value === 'implemented' ? 'heroicon-s-check-circle' : 'heroicon-o-check-circle', 'w-5 h-5')
                                        </button>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm leading-relaxed {{ $d->status?->value === 'discarded' ? 'text-[color:var(--nx-faint)] line-through' : 'text-[color:var(--nx-text)]' }}">{{ $d->text }}</div>
                                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-[color:var(--nx-muted)]">
                                            <span class="font-medium text-[color:var(--nx-text)]">{{ $format->decisionKindLabel($d->kind) }}</span>
                                            @if($d->agenda_item_id && ($ai = $items->firstWhere('id', $d->agenda_item_id)))<span>zu {{ $ai->title }}</span>@endif
                                            @if($d->ownerUser || $d->ownerAgent)<span>{{ $d->ownerUser?->name ?? $d->ownerAgent?->name }}</span>@endif
                                            @if($d->due_date)<span class="{{ $od ? 'font-medium text-[color:var(--nx-danger)]' : '' }}">fällig {{ $d->due_date->format('d.m.Y') }}</span>@endif
                                            @if($d->linked_task_id)<span>Aufgabe #{{ $d->linked_task_id }}</span>@endif
                                        </div>
                                    </div>
                                    <x-nx-badge :variant="$od ? 'danger' : \Platform\ManagementBook\Livewire\Meeting\Show::decisionBadge($d->status)">{{ $od ? 'Überfällig' : $d->status?->label() }}</x-nx-badge>
                                    @if($canEdit)
                                        <div class="flex shrink-0 gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                            <button type="button" wire:click="openDecision({{ $d->id }})" class="text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]" title="Bearbeiten">@svg('heroicon-o-pencil-square', 'w-4 h-4')</button>
                                            <button type="button" wire:click="deleteDecision({{ $d->id }})" wire:confirm="Beschluss löschen?" class="text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)]" title="Löschen">@svg('heroicon-o-trash', 'w-4 h-4')</button>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="m-0 px-5 py-6 text-sm leading-relaxed text-[color:var(--nx-muted)]">Noch keine Beschlüsse. Beschlüsse entstehen aus den Agenda-Punkten – am schnellsten über „Beschluss zu diesem Punkt“.</p>
                            @endforelse
                        </x-nx-card>
                    </section>

                    {{-- Protokoll --}}
                    <section class="space-y-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="max-w-2xl space-y-1">
                                <h2 class="m-0 text-base font-semibold text-[color:var(--nx-text)]">Protokoll</h2>
                                <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                                    Das Protokoll hält fest, was verstanden und entschieden wurde – nicht jedes Wort. Erst mit der Freigabe gilt es als gemeinsamer Stand.
                                </p>
                            </div>
                            @if($canEdit)
                                <x-nx-button variant="secondary" size="sm" wire:click="openMinutes">@svg('heroicon-o-plus', 'w-4 h-4') Protokoll</x-nx-button>
                            @endif
                        </div>
                        @forelse($meeting->minutes as $min)
                            <x-nx-card wire:key="minutes-{{ $min->id }}">
                                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    @if($min->approved_at)
                                        <x-nx-badge variant="success" dot>Freigegeben {{ $min->approved_at->format('d.m.Y') }}{{ $min->approver ? ' · ' . $min->approver->name : '' }}</x-nx-badge>
                                    @else
                                        <x-nx-badge variant="warning" dot>Entwurf</x-nx-badge>
                                    @endif
                                    @if($canEdit)
                                        <div class="flex gap-2">
                                            <x-nx-button variant="ghost" size="sm" wire:click="openMinutes({{ $min->id }})">Bearbeiten</x-nx-button>
                                            <x-nx-button size="sm" wire:click="toggleMinutesApproval({{ $min->id }})">{{ $min->approved_at ? 'Freigabe aufheben' : 'Freigeben' }}</x-nx-button>
                                            <x-nx-button variant="ghost" size="sm" icon wire:click="deleteMinutes({{ $min->id }})" wire:confirm="Protokoll löschen?" title="Löschen">@svg('heroicon-o-trash', 'w-4 h-4')</x-nx-button>
                                        </div>
                                    @endif
                                </div>
                                <div class="prose max-w-[68ch] text-[15px] leading-relaxed text-[color:var(--nx-text)]">
                                    {!! \Illuminate\Support\Str::markdown($min->content ?? '', ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                                </div>
                            </x-nx-card>
                        @empty
                            <x-nx-card>
                                <div class="space-y-3 p-2">
                                    <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">Noch kein Protokoll. Beim Anlegen werden die Agenda-Punkte als Gliederung vorgeschlagen.</p>
                                    @if($canEdit)
                                        <x-nx-button size="sm" wire:click="openMinutes">Protokoll beginnen</x-nx-button>
                                    @endif
                                </div>
                            </x-nx-card>
                        @endforelse
                    </section>
                </div>

                {{-- Seitenspalte: Teilnehmer, Unterlagen, Haltung + Orientierung --}}
                <div class="space-y-8 self-start xl:sticky xl:top-6">
                    <section class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Teilnehmer <span class="font-normal text-[color:var(--nx-faint)]">{{ $meeting->attendees->count() }}</span></h2>
                            @if($canEdit)
                                <x-nx-button variant="ghost" size="sm" icon wire:click="openAttendee" title="Teilnehmer hinzufügen">@svg('heroicon-o-plus', 'w-4 h-4')</x-nx-button>
                            @endif
                        </div>
                        <x-nx-card flush>
                            @forelse($meeting->attendees as $a)
                                <div class="group flex items-center gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-b-0" wire:key="att-{{ $a->id }}">
                                    <button type="button" @if($canEdit) wire:click="toggleAttended({{ $a->id }})" @endif
                                            title="{{ $a->attended ? 'anwesend' : 'nicht anwesend' }}"
                                            class="shrink-0 {{ $a->attended ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-faint)]' }} {{ $canEdit ? 'hover:text-[color:var(--nx-text)]' : 'cursor-default' }}">
                                        @svg($a->attended ? 'heroicon-s-check-circle' : 'heroicon-o-minus-circle', 'w-5 h-5')
                                    </button>
                                    <span class="min-w-0 flex-1 truncate text-sm">
                                        {{ $a->user?->name ?? $a->agent?->name ?? '–' }}
                                        @if($a->agent_id)<span class="text-xs text-[color:var(--nx-faint)]">Agent</span>@endif
                                    </span>
                                    @if($a->role)<span class="text-xs text-[color:var(--nx-muted)]">{{ \Platform\ManagementBook\Enums\CommitteeMemberRole::tryFrom($a->role)?->label() ?? $a->role }}</span>@endif
                                    @if($canEdit)
                                        <button type="button" wire:click="removeAttendee({{ $a->id }})" class="opacity-0 transition-opacity group-hover:opacity-100 text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)]" title="Entfernen">@svg('heroicon-o-x-mark', 'w-4 h-4')</button>
                                    @endif
                                </div>
                            @empty
                                <p class="m-0 px-4 py-4 text-sm leading-relaxed text-[color:var(--nx-muted)]">Noch keine Teilnehmer. Anwesenheit hakst du nach der Sitzung ab.</p>
                            @endforelse
                            @if($canEdit && $committee && $committee->members->isNotEmpty())
                                <div class="border-t border-[color:var(--nx-line)] px-4 py-2.5">
                                    <x-nx-button variant="ghost" size="sm" wire:click="adoptMembers">@svg('heroicon-o-arrow-down-tray', 'w-4 h-4') Gremiumsmitglieder übernehmen</x-nx-button>
                                </div>
                            @endif
                        </x-nx-card>
                    </section>

                    <section class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Unterlagen <span class="font-normal text-[color:var(--nx-faint)]">{{ $meeting->documents->count() }}</span></h2>
                            @if($canEdit)
                                <x-nx-button variant="ghost" size="sm" icon wire:click="openDocument" title="Unterlage hinzufügen">@svg('heroicon-o-plus', 'w-4 h-4')</x-nx-button>
                            @endif
                        </div>
                        <x-nx-card flush>
                            @forelse($meeting->documents as $doc)
                                <div class="group flex items-start gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-b-0" wire:key="doc-{{ $doc->id }}">
                                    @svg('heroicon-o-document', 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm">{{ $doc->title }}</div>
                                        <div class="text-xs text-[color:var(--nx-faint)]">
                                            {{ collect([$doc->type, $doc->agenda_item_id ? optional($items->firstWhere('id', $doc->agenda_item_id))->title : null])->filter()->implode(' · ') }}
                                        </div>
                                        @foreach($documentFiles[$doc->id] ?? [] as $file)
                                            <div class="mt-0.5 inline-flex items-center gap-1 text-xs text-[color:var(--nx-muted)]">@svg('heroicon-o-paper-clip', 'w-3 h-3') {{ $file }}</div>
                                        @endforeach
                                    </div>
                                    @if($canEdit)
                                        <button type="button" wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Unterlage entfernen?" class="opacity-0 transition-opacity group-hover:opacity-100 text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)]" title="Entfernen">@svg('heroicon-o-x-mark', 'w-4 h-4')</button>
                                    @endif
                                </div>
                            @empty
                                <p class="m-0 px-4 py-4 text-sm leading-relaxed text-[color:var(--nx-muted)]">Keine Unterlagen. Vorlagen vorab verteilen spart Zeit für das Gespräch.</p>
                            @endforelse
                        </x-nx-card>
                    </section>

                    {{-- Haltung / Rolle des Gremiums (aus dem Format) --}}
                    @if($stance)
                        <div class="space-y-2 rounded-[8px] border border-[color:var(--nx-line)] p-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">{{ $stance['title'] }}</div>
                            <ol class="m-0 space-y-1.5 pl-4 text-sm leading-relaxed text-[color:var(--nx-text)]">
                                @foreach($stance['items'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ol>
                            <p class="m-0 text-xs leading-relaxed text-[color:var(--nx-faint)]">{{ $stance['note'] }}</p>
                        </div>
                    @endif

                    @include('management-book::livewire.partials.guide-rail', ['area' => $format->guideArea()])
                </div>
            </div>
        </div>
    </x-ui-page-container>

    @include('management-book::livewire.meeting.partials.modals')
</x-ui-page>
