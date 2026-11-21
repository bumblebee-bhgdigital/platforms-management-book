{{--
    Gremium-Seite (nx): breiter Kopf, Hauptspalte (Kennzahlen, Sitzungen, offene Beschlüsse, Mitglieder – je mit Erklärung)
    und Orientierungsspalte (Fall-Sortierer beim Lenkungsausschuss + Guide).
--}}
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$committee->name" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Management Books', 'href' => route('management-book.dashboard'), 'icon' => 'book-open'],
            ['label' => $book?->title ?? 'Book', 'href' => $book ? route('management-book.books.show', $book) . '?tab=committees' : null],
            ['label' => $committee->name],
        ]">
            @if($canEdit)
                <x-nx-dropdown label="Aktionen">
                    <x-nx-dropdown-item wire:click="openMeeting">@svg('heroicon-o-calendar-days', 'w-4 h-4') Sitzung anlegen</x-nx-dropdown-item>
                    <x-nx-dropdown-item wire:click="openEdit">@svg('heroicon-o-pencil-square', 'w-4 h-4') Bearbeiten</x-nx-dropdown-item>
                    @if($committee->status?->value !== 'active')
                        <x-nx-dropdown-item wire:click="setStatus('active')">@svg('heroicon-o-play', 'w-4 h-4') Aktivieren</x-nx-dropdown-item>
                    @endif
                    @if($committee->status?->value === 'active')
                        <x-nx-dropdown-item wire:click="setStatus('dormant')">@svg('heroicon-o-pause', 'w-4 h-4') Ruhen lassen</x-nx-dropdown-item>
                    @endif
                    @if($committee->status?->value !== 'dissolved')
                        <x-nx-dropdown-item wire:click="setStatus('dissolved')" wire:confirm="Gremium auflösen? Sitzungen bleiben erhalten.">@svg('heroicon-o-archive-box', 'w-4 h-4') Auflösen</x-nx-dropdown-item>
                    @endif
                    <x-nx-dropdown-divider />
                    <x-nx-dropdown-item variant="danger" wire:click="deleteCommittee" wire:confirm="Gremium löschen? (Soft-Delete)">@svg('heroicon-o-trash', 'w-4 h-4') Löschen</x-nx-dropdown-item>
                </x-nx-dropdown>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="full" spacing="space-y-8">
        @php
            $isSteering = $committee->type?->value === 'steering_committee';
            $overdue = $openDecisions->filter(fn ($d) => $d->due_date && $d->due_date->isPast())->count();
            $capTotal = collect($capability);
            $capPct = $capTotal->sum('max') > 0 ? (int) round($capTotal->sum('done') / $capTotal->sum('max') * 100) : null;
            $roleHelp = [
                'chair' => 'leitet die Sitzung und achtet auf die Haltung: Hilfe statt Macht.',
                'secretary' => 'hält Protokoll und Beschlüsse fest.',
                'member' => 'bringt Themen aus dem eigenen Verantwortungsbereich ein.',
                'guest' => 'wird zu einzelnen Punkten hinzugezogen.',
            ];
        @endphp
        <div class="mx-auto w-full max-w-[1440px] space-y-8">
            {{-- Kopf --}}
            <div class="space-y-3">
                <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Gremium · {{ $book?->title }}</div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="m-0 text-3xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $committee->name }}</h1>
                    <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Book\Committees::typeBadge($committee->type)">{{ $committee->type?->label() }}</x-nx-badge>
                    <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Book\Committees::statusBadge($committee->status)" dot>{{ $committee->status?->label() }}</x-nx-badge>
                </div>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-[color:var(--nx-muted)]">
                    <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-user', 'w-4 h-4 text-[color:var(--nx-faint)]') Vorsitz {{ $committee->chair?->name ?? 'offen' }}</span>
                    <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-arrow-path', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $committee->cadence ?: 'kein Rhythmus hinterlegt' }}</span>
                    @if($committee->responsibleAgent)
                        <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-cpu-chip', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $committee->responsibleAgent->name }}</span>
                    @endif
                </div>
                @if($committee->description)
                    <p class="m-0 max-w-3xl whitespace-pre-line text-[15px] leading-relaxed text-[color:var(--nx-text)]">{{ $committee->description }}</p>
                @elseif($canEdit)
                    <button type="button" wire:click="openEdit" class="text-sm text-[color:var(--nx-muted)] underline hover:text-[color:var(--nx-text)]">
                        Zweck beschreiben: Wofür ist dieses Gremium da – und was gehört nicht hierher?
                    </button>
                @endif
            </div>

            <div class="grid gap-10 xl:grid-cols-[minmax(0,1fr)_300px]">
                {{-- Hauptspalte --}}
                <div class="min-w-0 space-y-10">
                    <x-nx-stat-grid :cols="4">
                        <x-nx-stat label="Anstehende Sitzungen" :value="$upcoming->count()" icon="heroicon-o-calendar-days"
                                   :hint="$upcoming->first()?->scheduled_at ? 'nächste ' . $upcoming->first()->scheduled_at->format('d.m.Y H:i') : 'keine geplant'" />
                        <x-nx-stat label="Gehaltene Sitzungen" :value="$past->where('status.value', 'held')->count()" icon="heroicon-o-check-circle" />
                        <x-nx-stat label="Offene Beschlüsse" :value="$openDecisions->count()" icon="heroicon-o-clipboard-document-check"
                                   :hint="$overdue ? $overdue . ' überfällig' : 'keine überfällig'" :accent="$overdue ? 'var(--nx-danger)' : null" />
                        <x-nx-stat label="Handlungsfähigkeit" :value="$capPct !== null ? $capPct . ' %' : '–'" icon="heroicon-o-bolt"
                                   :hint="$capPct !== null ? 'erfüllte Kriterien nach dem LA' : 'noch keine Kriterien bewertet'"
                                   :accent="$capPct === null ? null : ($capPct >= 80 ? 'var(--nx-success)' : ($capPct >= 50 ? 'var(--nx-warning)' : 'var(--nx-danger)'))" />
                    </x-nx-stat-grid>

                    {{-- Sitzungen --}}
                    <section class="space-y-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="max-w-2xl space-y-1">
                                <h2 class="m-0 text-base font-semibold text-[color:var(--nx-text)]">Sitzungen</h2>
                                <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                                    Plane den Rhythmus, nicht nur den nächsten Termin. Ein Gremium wirkt, wenn es verlässlich tagt – dann können Bereiche Themen rechtzeitig einbringen, statt sie zu horten.
                                </p>
                            </div>
                            @if($canEdit)
                                <x-nx-button size="sm" wire:click="openMeeting">@svg('heroicon-o-plus', 'w-4 h-4') Sitzung</x-nx-button>
                            @endif
                        </div>

                        <div class="space-y-2">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Anstehend · {{ $upcoming->count() }}</div>
                            <x-nx-card flush>
                                @forelse($upcoming as $m)
                                    <div class="border-b border-[color:var(--nx-line)] last:border-b-0" wire:key="up-{{ $m->id }}">
                                        <x-nx-list-item icon="heroicon-o-calendar" :href="route('management-book.meetings.show', $m)" :title="$m->title"
                                                        :subtitle="collect([$m->scheduled_at?->translatedFormat('D, d.m.Y H:i'), $m->location, $m->agenda_items_count . ' Agenda-Punkte'])->filter()->implode(' · ')">
                                            <x-slot name="trailing">
                                                <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Committee\Show::meetingBadge($m->status)" dot>{{ $m->status?->label() }}</x-nx-badge>
                                            </x-slot>
                                        </x-nx-list-item>
                                    </div>
                                @empty
                                    <div class="space-y-3 px-5 py-6">
                                        <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                                            Keine Sitzung geplant. Lege die nächsten Termine gleich für ein paar Monate an{{ $committee->cadence ? ' – Rhythmus: ' . $committee->cadence : '' }}.
                                        </p>
                                        @if($canEdit)
                                            <x-nx-button size="sm" wire:click="openMeeting">Nächste Sitzung planen</x-nx-button>
                                        @endif
                                    </div>
                                @endforelse
                            </x-nx-card>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Vergangen · {{ $past->count() }}</div>
                                <div class="text-xs text-[color:var(--nx-faint)]">Maßstab: Ist die Organisation danach handlungsfähiger als vorher?</div>
                            </div>
                            <x-nx-card flush>
                                @forelse($past as $m)
                                    @php $cap = $capability[$m->id] ?? null; @endphp
                                    <div class="border-b border-[color:var(--nx-line)] last:border-b-0" wire:key="past-{{ $m->id }}">
                                        <x-nx-list-item icon="heroicon-o-document-text" :href="route('management-book.meetings.show', $m)" :title="$m->title"
                                                        :subtitle="collect([$m->scheduled_at?->format('d.m.Y'), $m->agenda_items_count . ' Punkte', $m->decisions_count . ' Beschlüsse'])->filter()->implode(' · ')">
                                            <x-slot name="trailing">
                                                <div class="flex items-center gap-2">
                                                    @if($cap)
                                                        @php $v = \Platform\ManagementBook\Enums\AgendaOutcomeCheck::verdict((int) round($cap['pct'] / 100 * 6)); @endphp
                                                        <x-nx-badge :variant="$v['variant']" title="{{ $cap['done'] }} von {{ $cap['max'] }} Kriterien">{{ $cap['pct'] }} % handlungsfähig</x-nx-badge>
                                                    @endif
                                                    <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Committee\Show::meetingBadge($m->status)" dot>{{ $m->status?->label() }}</x-nx-badge>
                                                </div>
                                            </x-slot>
                                        </x-nx-list-item>
                                    </div>
                                @empty
                                    <p class="m-0 px-5 py-6 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                                        Noch keine vergangenen Sitzungen. Nach jeder Sitzung zeigt sich hier, wie handlungsfähig die Organisation danach war.
                                    </p>
                                @endforelse
                            </x-nx-card>
                        </div>
                    </section>

                    {{-- Offene Beschlüsse --}}
                    <section class="space-y-4">
                        <div class="max-w-2xl space-y-1">
                            <h2 class="m-0 text-base font-semibold text-[color:var(--nx-text)]">Offene Beschlüsse <span class="font-normal text-[color:var(--nx-faint)]">{{ $openDecisions->count() }}</span></h2>
                            <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                                Ein Beschluss ist erst wirksam, wenn er umgesetzt ist. Verfolgt ihn bis dahin – und verwerft ihn bewusst, wenn er sich überholt hat.
                            </p>
                        </div>
                        <x-nx-card flush>
                            @forelse($openDecisions as $d)
                                @php $overdueD = $d->due_date && $d->due_date->isPast(); @endphp
                                <div class="flex flex-col gap-3 border-b border-[color:var(--nx-line)] px-5 py-4 last:border-b-0 sm:flex-row sm:items-start sm:justify-between" wire:key="dec-{{ $d->id }}">
                                    <div class="min-w-0 space-y-1">
                                        <div class="text-sm leading-relaxed text-[color:var(--nx-text)]">{{ $d->text }}</div>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[color:var(--nx-muted)]">
                                            <a href="{{ route('management-book.meetings.show', $d->meeting_id) }}" wire:navigate class="hover:underline">{{ $d->meeting?->title }}</a>
                                            @if($d->ownerUser || $d->ownerAgent)<span>{{ $d->ownerUser?->name ?? $d->ownerAgent?->name }}</span>@endif
                                            @if($d->due_date)
                                                <span class="{{ $overdueD ? 'font-medium text-[color:var(--nx-danger)]' : '' }}">fällig {{ $d->due_date->format('d.m.Y') }}{{ $overdueD ? ' · überfällig' : '' }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($canEdit)
                                        <div class="flex shrink-0 gap-2">
                                            <x-nx-button size="sm" wire:click="setDecisionStatus({{ $d->id }}, 'implemented')">@svg('heroicon-o-check', 'w-4 h-4') Umgesetzt</x-nx-button>
                                            <x-nx-button variant="ghost" size="sm" wire:click="setDecisionStatus({{ $d->id }}, 'discarded')">Verwerfen</x-nx-button>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="m-0 px-5 py-6 text-sm leading-relaxed text-[color:var(--nx-muted)]">Keine offenen Beschlüsse – alles Beschlossene ist umgesetzt oder bewusst verworfen.</p>
                            @endforelse
                        </x-nx-card>
                    </section>

                    {{-- Präsentationen am Gremium (Slides, lose gekoppelt) --}}
                    @if(\Platform\ManagementBook\Livewire\LinkedDecks::available())
                        <livewire:management-book.linked-decks :linkable-type="\Platform\ManagementBook\Models\ManagementBookCommittee::MORPH_ALIAS" :linkable-id="$committee->id" :compact="true" :key="'decks-committee-' . $committee->id" />
                    @endif

                    {{-- Mitglieder --}}
                    <section class="space-y-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="max-w-2xl space-y-1">
                                <h2 class="m-0 text-base font-semibold text-[color:var(--nx-text)]">Mitglieder <span class="font-normal text-[color:var(--nx-faint)]">{{ $committee->members->count() }}</span></h2>
                                <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                                    Wer am Tisch sitzt, bringt die Perspektive seines Bereichs ein. Der Vorsitz leitet und achtet auf die Haltung, die Protokollführung hält Beschlüsse fest. Agenten können mitarbeiten wie Menschen.
                                </p>
                            </div>
                            @if($canEdit)
                                <x-nx-button variant="secondary" size="sm" wire:click="openMember">@svg('heroicon-o-plus', 'w-4 h-4') Mitglied</x-nx-button>
                            @endif
                        </div>
                        <x-nx-card flush>
                            @forelse($committee->members->sortBy(fn ($m) => array_search($m->role?->value, ['chair', 'secretary', 'member', 'guest'])) as $member)
                                <div class="group flex items-center gap-3 border-b border-[color:var(--nx-line)] px-5 py-3 last:border-b-0" wire:key="member-{{ $member->id }}">
                                    @svg($member->agent_id ? 'heroicon-o-cpu-chip' : 'heroicon-o-user', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm text-[color:var(--nx-text)]">{{ $member->user?->name ?? $member->agent?->name ?? '–' }}</span>
                                        @if(isset($roleHelp[$member->role?->value]))
                                            <span class="block truncate text-xs text-[color:var(--nx-faint)]">{{ $member->role?->label() }} {{ $roleHelp[$member->role->value] }}</span>
                                        @endif
                                    </span>
                                    <x-nx-badge :variant="$member->role?->value === 'chair' ? 'accent' : 'neutral'">{{ $member->role?->label() }}</x-nx-badge>
                                    @if($canEdit)
                                        <button type="button" wire:click="removeMember({{ $member->id }})" wire:confirm="Mitglied entfernen?"
                                                class="opacity-0 transition-opacity group-hover:opacity-100 text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)]" title="Entfernen">
                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                        </button>
                                    @endif
                                </div>
                            @empty
                                <p class="m-0 px-5 py-6 text-sm leading-relaxed text-[color:var(--nx-muted)]">Noch keine Mitglieder. Beginne mit Vorsitz und Protokollführung, dann die Verantwortlichen der Bereiche.</p>
                            @endforelse
                        </x-nx-card>
                    </section>
                </div>

                {{-- Orientierungsspalte --}}
                <div class="space-y-6 self-start xl:sticky xl:top-6">
                    @if($isSteering)
                        <div class="space-y-2 rounded-[8px] border border-[color:var(--nx-line)] p-4">
                            <div class="flex items-center gap-2 text-sm font-semibold text-[color:var(--nx-text)]">
                                @svg('heroicon-o-arrows-right-left', 'w-4 h-4 text-[color:var(--nx-info)]') Gehört das hier hin?
                            </div>
                            <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                                Eskalieren, wenn andere Einheiten, gemeinsame Ressourcen oder strategische Prioritäten betroffen sind – sonst lokal lösen.
                            </p>
                        </div>
                    @endif
                    @include('management-book::livewire.partials.guide-rail', ['area' => $isSteering ? 'committee' : 'committees'])
                </div>
            </div>
        </div>
    </x-ui-page-container>

    {{-- Bearbeiten --}}
    <x-nx-modal wire:model="showEdit" size="md">
        <x-slot name="header">Gremium bearbeiten</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="form.name" label="Name" wire:model="form.name" required />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-nx-input-select name="form.type" label="Typ" :options="$typeOptions" wire:model="form.type" />
                <x-nx-input-select name="form.status" label="Status" :options="$statusOptions" wire:model="form.status" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-nx-input-text name="form.cadence" label="Rhythmus" wire:model="form.cadence" placeholder="z. B. monatlich" />
                <x-nx-input-select name="form.chair_user_id" label="Vorsitz" :options="$userOptions" nullable="true" nullLabel="– offen –" wire:model="form.chair_user_id" />
            </div>
            <x-nx-input-textarea name="form.description" label="Zweck" wire:model="form.description" rows="3" />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showEdit', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Mitglied --}}
    <x-nx-modal wire:model="showMember" size="sm">
        <x-slot name="header">Mitglied hinzufügen</x-slot>
        <div class="space-y-4">
            <x-nx-input-select name="memberForm.user_id" label="Person oder Agent" :options="$userOptions" nullable="true" nullLabel="– wählen –" wire:model="memberForm.user_id" required />
            <x-nx-input-select name="memberForm.role" label="Rolle" :options="$roleOptions" wire:model="memberForm.role" />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showMember', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="addMember">Hinzufügen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Sitzung --}}
    <x-nx-modal wire:model="showMeeting" size="md">
        <x-slot name="header">Sitzung anlegen</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="meetingForm.title" label="Titel" wire:model="meetingForm.title" required />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-nx-input-datetime name="meetingForm.scheduled_at" label="Termin" wire:model="meetingForm.scheduled_at" required />
                <x-nx-input-number name="meetingForm.duration_minutes" label="Dauer (Min.)" wire:model="meetingForm.duration_minutes" />
            </div>
            <x-nx-input-text name="meetingForm.location" label="Ort" wire:model="meetingForm.location" />
            <p class="m-0 text-xs text-[color:var(--nx-muted)]">Agenda, Teilnehmer und Unterlagen pflegt ihr anschließend in der Sitzung – nichts wird automatisch übernommen.</p>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showMeeting', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="createMeeting">Anlegen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
