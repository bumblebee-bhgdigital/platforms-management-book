{{--
    Gremium-Seite (nx): Kopf mit Typ/Status/Rhythmus/Vorsitz, Mitglieder, Sitzungen (anstehend/vergangen)
    und offene Beschlüsse über alle Sitzungen.
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

    <x-ui-page-container width="contained" spacing="space-y-6">
        {{-- Kopf --}}
        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $committee->name }}</h1>
                <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Book\Committees::typeBadge($committee->type)">{{ $committee->type?->label() }}</x-nx-badge>
                <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Book\Committees::statusBadge($committee->status)" dot>{{ $committee->status?->label() }}</x-nx-badge>
            </div>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-[color:var(--nx-muted)]">
                <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-user', 'w-4 h-4 text-[color:var(--nx-faint)]') Vorsitz {{ $committee->chair?->name ?? '–' }}</span>
                <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-arrow-path', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $committee->cadence ?: 'kein Rhythmus hinterlegt' }}</span>
                @if($committee->responsibleAgent)
                    <span class="inline-flex items-center gap-1.5">@svg('heroicon-o-cpu-chip', 'w-4 h-4 text-[color:var(--nx-faint)]') {{ $committee->responsibleAgent->name }}</span>
                @endif
            </div>
            @if($committee->description)
                <p class="m-0 max-w-3xl whitespace-pre-line text-sm text-[color:var(--nx-muted)]">{{ $committee->description }}</p>
            @endif
        </div>

        @if($committee->type?->value === 'steering_committee')
            <x-nx-callout variant="info" icon="heroicon-o-arrows-right-left" title="Lokal vor übergreifend">
                Was innerhalb eines Verantwortungsbereichs gelöst werden kann, wird dort gelöst. Der Lenkungsausschuss beginnt dort,
                wo lokale Handlungsfähigkeit nicht mehr ausreicht – er übernimmt keine Verantwortung, er macht sie möglich.
                Eskalieren heißt hier: professionell führen.
            </x-nx-callout>
        @endif

        <x-nx-stat-grid :cols="4">
            <x-nx-stat label="Anstehende Sitzungen" :value="$upcoming->count()" icon="heroicon-o-calendar-days"
                       :hint="$upcoming->first()?->scheduled_at ? 'nächste ' . $upcoming->first()->scheduled_at->format('d.m.Y H:i') : 'keine geplant'" />
            <x-nx-stat label="Gehaltene Sitzungen" :value="$past->where('status.value', 'held')->count()" icon="heroicon-o-check-circle" />
            <x-nx-stat label="Offene Beschlüsse" :value="$openDecisions->count()" icon="heroicon-o-clipboard-document-check"
                       :hint="($overdue = $openDecisions->filter(fn ($d) => $d->due_date && $d->due_date->isPast())->count()) ? $overdue . ' überfällig' : 'keine überfällig'"
                       :accent="$overdue ? 'var(--nx-danger)' : null" />
            @php
                $capTotal = collect($capability);
                $capPct = $capTotal->sum('max') > 0 ? (int) round($capTotal->sum('done') / $capTotal->sum('max') * 100) : null;
            @endphp
            <x-nx-stat label="Handlungsfähigkeit" :value="$capPct !== null ? $capPct . ' %' : '–'" icon="heroicon-o-bolt"
                       hint="erfüllte Kriterien nach dem LA" :accent="$capPct === null ? null : ($capPct >= 80 ? 'var(--nx-success)' : ($capPct >= 50 ? 'var(--nx-warning)' : 'var(--nx-danger)'))" />
        </x-nx-stat-grid>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Sitzungen --}}
            <div class="space-y-6 lg:col-span-2">
                <x-nx-section icon="heroicon-o-calendar-days" title="Anstehende Sitzungen" :hint="$upcoming->count()">
                    @if($canEdit)
                        <x-slot name="action">
                            <x-nx-button size="sm" wire:click="openMeeting">@svg('heroicon-o-plus', 'w-4 h-4') Sitzung</x-nx-button>
                        </x-slot>
                    @endif
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
                            <x-nx-empty icon="heroicon-o-calendar">Keine Sitzung geplant.</x-nx-empty>
                        @endforelse
                    </x-nx-card>
                </x-nx-section>

                <x-nx-section icon="heroicon-o-clock" title="Vergangene Sitzungen" :hint="$past->count()"
                              description="Maßstab je Sitzung: Ist die Organisation danach handlungsfähiger als vorher?">
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
                            <x-nx-empty icon="heroicon-o-clock">Noch keine vergangenen Sitzungen.</x-nx-empty>
                        @endforelse
                    </x-nx-card>
                </x-nx-section>
            </div>

            {{-- Seitenspalte --}}
            <div class="space-y-6">
                <x-nx-section icon="heroicon-o-users" title="Mitglieder" :hint="$committee->members->count()">
                    @if($canEdit)
                        <x-slot name="action">
                            <x-nx-button variant="ghost" size="sm" icon wire:click="openMember" title="Mitglied hinzufügen">@svg('heroicon-o-plus', 'w-4 h-4')</x-nx-button>
                        </x-slot>
                    @endif
                    <x-nx-card flush>
                        @forelse($committee->members->sortBy(fn ($m) => array_search($m->role?->value, ['chair', 'secretary', 'member', 'guest'])) as $member)
                            <div class="group flex items-center gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-b-0" wire:key="member-{{ $member->id }}">
                                @svg($member->agent_id ? 'heroicon-o-cpu-chip' : 'heroicon-o-user', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                <span class="min-w-0 flex-1 truncate text-sm">{{ $member->user?->name ?? $member->agent?->name ?? '–' }}</span>
                                <x-nx-badge :variant="$member->role?->value === 'chair' ? 'accent' : 'neutral'">{{ $member->role?->label() }}</x-nx-badge>
                                @if($canEdit)
                                    <button type="button" wire:click="removeMember({{ $member->id }})" wire:confirm="Mitglied entfernen?"
                                            class="opacity-0 transition-opacity group-hover:opacity-100 text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)]" title="Entfernen">
                                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                                    </button>
                                @endif
                            </div>
                        @empty
                            <x-nx-empty icon="heroicon-o-users">Noch keine Mitglieder.</x-nx-empty>
                        @endforelse
                    </x-nx-card>
                </x-nx-section>

                <x-nx-section icon="heroicon-o-clipboard-document-check" title="Offene Beschlüsse" :hint="$openDecisions->count()">
                    <x-nx-card flush>
                        @forelse($openDecisions as $d)
                            @php $overdueD = $d->due_date && $d->due_date->isPast(); @endphp
                            <div class="border-b border-[color:var(--nx-line)] px-4 py-3 last:border-b-0" wire:key="dec-{{ $d->id }}">
                                <div class="text-sm text-[color:var(--nx-text)]">{{ $d->text }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[color:var(--nx-muted)]">
                                    <a href="{{ route('management-book.meetings.show', $d->meeting_id) }}" wire:navigate class="hover:underline">{{ $d->meeting?->title }}</a>
                                    @if($d->ownerUser || $d->ownerAgent)<span>{{ $d->ownerUser?->name ?? $d->ownerAgent?->name }}</span>@endif
                                    @if($d->due_date)
                                        <span class="{{ $overdueD ? 'font-medium text-[color:var(--nx-danger)]' : '' }}">fällig {{ $d->due_date->format('d.m.Y') }}</span>
                                    @endif
                                </div>
                                @if($canEdit)
                                    <div class="mt-2 flex gap-2">
                                        <x-nx-button size="sm" wire:click="setDecisionStatus({{ $d->id }}, 'implemented')">@svg('heroicon-o-check', 'w-4 h-4') Umgesetzt</x-nx-button>
                                        <x-nx-button variant="ghost" size="sm" wire:click="setDecisionStatus({{ $d->id }}, 'discarded')">Verwerfen</x-nx-button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <x-nx-empty icon="heroicon-o-clipboard-document-check">Keine offenen Beschlüsse.</x-nx-empty>
                        @endforelse
                    </x-nx-card>
                </x-nx-section>
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
