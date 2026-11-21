{{--
    Sitzungsseite (nx) mit Lenkungsausschuss-Leitfaden: Agenda (vier Fragen + Handlungsfähigkeit),
    Beschlüsse, Teilnehmer, Protokoll, Unterlagen. Haltung: Hilfe statt Macht.
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

    <x-ui-page-container width="contained" spacing="space-y-6">
        {{-- Kopf --}}
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $meeting->title }}</h1>
                <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Committee\Show::meetingBadge($meeting->status)" dot>{{ $meeting->status?->label() }}</x-nx-badge>
            </div>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-[color:var(--nx-muted)]">
                <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-calendar-days', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $meeting->scheduled_at?->translatedFormat('l, d.m.Y · H:i') ?? 'kein Termin' }}</span>
                @if($meeting->duration_minutes)<span class="inline-flex items-center gap-1.5">@svg('heroicon-o-clock', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $meeting->duration_minutes }} Min.</span>@endif
                @if($meeting->location)<span class="inline-flex items-center gap-1.5">@svg('heroicon-o-map-pin', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $meeting->location }}</span>@endif
                @if($committee)<span class="inline-flex items-center gap-1.5">@svg('heroicon-o-user-group', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $committee->name }}</span>@endif
            </div>
            @if($canEdit)
                <div class="flex flex-wrap items-center gap-1">
                    <span class="mr-1 text-xs text-[color:var(--nx-faint)]">Status:</span>
                    @foreach($statusOptions as $s)
                        <button type="button" wire:click="setStatus('{{ $s->value }}')"
                                class="rounded-full px-2.5 py-0.5 text-xs transition-colors {{ $meeting->status === $s ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">
                            {{ $s->label() }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Zusammenfassung --}}
        @php
            $openDec = $decisions->where('status.value', 'open');
            $overdueDec = $openDec->filter(fn ($d) => $d->due_date && $d->due_date->isPast())->count();
            $present = $meeting->attendees->where('attended', true)->count();
            $planned = $items->sum('duration_minutes');
        @endphp
        <x-nx-stat-grid :cols="4">
            <x-nx-stat label="Agenda" :value="$items->count() . ' Punkte'" icon="heroicon-o-list-bullet"
                       :hint="($capability['guided'] ? $capability['guided'] . ' mit Leitfaden' : 'noch ohne Leitfaden') . ($planned ? ' · ' . $planned . ' Min.' : '')" />
            <x-nx-stat label="Handlungsfähigkeit" :value="$capability['pct'] !== null ? $capability['pct'] . ' %' : '–'" icon="heroicon-o-bolt"
                       :hint="$capability['max'] ? $capability['done'] . ' von ' . $capability['max'] . ' Kriterien erfüllt' : 'nach dem LA bewerten'"
                       :accent="$capability['pct'] === null ? null : ($capability['pct'] >= 80 ? 'var(--nx-success)' : ($capability['pct'] >= 50 ? 'var(--nx-warning)' : 'var(--nx-danger)'))" />
            <x-nx-stat label="Offene Beschlüsse" :value="$openDec->count()" icon="heroicon-o-clipboard-document-check"
                       :hint="$overdueDec ? $overdueDec . ' überfällig' : $decisions->count() . ' insgesamt'" :accent="$overdueDec ? 'var(--nx-danger)' : null" />
            <x-nx-stat label="Teilnehmer" :value="$present . ' / ' . $meeting->attendees->count()" icon="heroicon-o-users" hint="anwesend / eingeladen" />
        </x-nx-stat-grid>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- Agenda --}}
                <x-nx-section icon="heroicon-o-list-bullet" title="Agenda" :hint="$items->count()"
                              description="Je Punkt vier Fragen: Was wollten wir erreichen? Was beobachten wir? Was hindert euch? Was könnt ihr selbst lösen – und wobei braucht ihr das Gesamtsystem?">
                    @if($canEdit)
                        <x-slot name="action">
                            <x-nx-button size="sm" wire:click="openItem">@svg('heroicon-o-plus', 'w-4 h-4') Punkt</x-nx-button>
                        </x-slot>
                    @endif

                    @forelse($items as $idx => $item)
                        @include('management-book::livewire.meeting.partials.agenda-item', ['item' => $item, 'idx' => $idx, 'last' => $loop->last])
                    @empty
                        <x-nx-card>
                            <x-nx-empty icon="heroicon-o-list-bullet">
                                Noch keine Agenda. In den LA gehört, was zwischen den Einheiten klemmt – nicht, was lokal lösbar ist.
                                @if($canEdit)
                                    <x-slot name="action"><x-nx-button size="sm" wire:click="openItem">Ersten Punkt anlegen</x-nx-button></x-slot>
                                @endif
                            </x-nx-empty>
                        </x-nx-card>
                    @endforelse
                </x-nx-section>

                {{-- Beschlüsse --}}
                <x-nx-section icon="heroicon-o-clipboard-document-check" title="Beschlüsse" :hint="$decisions->count()"
                              description="Auch „zurück an den Bereich“ ist ein guter Beschluss: Regulation heißt nicht Zentralisierung.">
                    @if($canEdit)
                        <x-slot name="action">
                            <x-nx-button variant="secondary" size="sm" wire:click="openDecision">@svg('heroicon-o-plus', 'w-4 h-4') Beschluss</x-nx-button>
                        </x-slot>
                    @endif
                    <x-nx-card flush>
                        @forelse($decisions as $d)
                            @php $od = $d->status?->value === 'open' && $d->due_date && $d->due_date->isPast(); @endphp
                            <div class="group flex items-start gap-3 border-b border-[color:var(--nx-line)] px-4 py-3 last:border-b-0" wire:key="decision-{{ $d->id }}">
                                @if($canEdit)
                                    <button type="button" title="{{ $d->status?->value === 'implemented' ? 'Wieder öffnen' : 'Als umgesetzt markieren' }}"
                                            wire:click="setDecisionStatus({{ $d->id }}, '{{ $d->status?->value === 'implemented' ? 'open' : 'implemented' }}')"
                                            class="mt-0.5 shrink-0 {{ $d->status?->value === 'implemented' ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)]' }}">
                                        @svg($d->status?->value === 'implemented' ? 'heroicon-s-check-circle' : 'heroicon-o-check-circle', 'w-5 h-5')
                                    </button>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm {{ $d->status?->value === 'discarded' ? 'text-[color:var(--nx-faint)] line-through' : 'text-[color:var(--nx-text)]' }}">{{ $d->text }}</div>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-[color:var(--nx-muted)]">
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
                            <x-nx-empty icon="heroicon-o-clipboard-document-check">Noch keine Beschlüsse.</x-nx-empty>
                        @endforelse
                    </x-nx-card>
                </x-nx-section>

                {{-- Protokoll --}}
                <x-nx-section icon="heroicon-o-document-text" title="Protokoll" :hint="$meeting->minutes->count() ?: null">
                    @if($canEdit)
                        <x-slot name="action">
                            <x-nx-button variant="secondary" size="sm" wire:click="openMinutes">@svg('heroicon-o-plus', 'w-4 h-4') Protokoll</x-nx-button>
                        </x-slot>
                    @endif
                    @forelse($meeting->minutes as $min)
                        <x-nx-card class="mb-3" wire:key="minutes-{{ $min->id }}">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
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
                            <div class="prose prose-sm max-w-none text-[color:var(--nx-text)]">
                                {!! \Illuminate\Support\Str::markdown($min->content ?? '', ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </x-nx-card>
                    @empty
                        <x-nx-card><x-nx-empty icon="heroicon-o-document-text">Noch kein Protokoll.</x-nx-empty></x-nx-card>
                    @endforelse
                </x-nx-section>
            </div>

            {{-- Seitenspalte --}}
            <div class="space-y-6">
                <x-nx-section icon="heroicon-o-users" title="Teilnehmer" :hint="$meeting->attendees->count()">
                    @if($canEdit)
                        <x-slot name="action">
                            <x-nx-button variant="ghost" size="sm" icon wire:click="openAttendee" title="Teilnehmer hinzufügen">@svg('heroicon-o-plus', 'w-4 h-4')</x-nx-button>
                        </x-slot>
                    @endif
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
                            <x-nx-empty icon="heroicon-o-users">Noch keine Teilnehmer.</x-nx-empty>
                        @endforelse
                        @if($canEdit && $committee && $committee->members->isNotEmpty())
                            <div class="border-t border-[color:var(--nx-line)] px-4 py-2.5">
                                <x-nx-button variant="ghost" size="sm" wire:click="adoptMembers">@svg('heroicon-o-arrow-down-tray', 'w-4 h-4') Gremiumsmitglieder übernehmen</x-nx-button>
                            </div>
                        @endif
                    </x-nx-card>
                </x-nx-section>

                <x-nx-section icon="heroicon-o-paper-clip" title="Unterlagen" :hint="$meeting->documents->count()">
                    @if($canEdit)
                        <x-slot name="action">
                            <x-nx-button variant="ghost" size="sm" icon wire:click="openDocument" title="Unterlage hinzufügen">@svg('heroicon-o-plus', 'w-4 h-4')</x-nx-button>
                        </x-slot>
                    @endif
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
                            <x-nx-empty icon="heroicon-o-paper-clip">Keine Unterlagen.</x-nx-empty>
                        @endforelse
                    </x-nx-card>
                </x-nx-section>

                <x-nx-card>
                    <div class="mb-2 text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Haltung im LA</div>
                    <ol class="m-0 space-y-1 pl-4 text-sm text-[color:var(--nx-muted)]">
                        <li>Was hindert euch daran?</li>
                        <li>Was könnt ihr selbst verändern?</li>
                        <li>Was müssen wir als Gesamtsystem verändern, damit ihr wirksam handeln könnt?</li>
                    </ol>
                    <p class="mb-0 mt-2 text-xs text-[color:var(--nx-faint)]">Maßstab: Ist die Organisation danach handlungsfähiger als vorher?</p>
                </x-nx-card>
            </div>
        </div>
    </x-ui-page-container>

    @include('management-book::livewire.meeting.partials.modals')
</x-ui-page>
