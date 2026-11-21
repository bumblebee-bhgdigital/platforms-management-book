{{-- Tab „Gremien“: Gremien des Books als Liste, Zeile öffnet die Gremium-Seite. --}}
<div class="space-y-6">
    <x-nx-section icon="heroicon-o-user-group" title="Gremien" :hint="$committees->count()"
                  description="Gremien halten Strategie und Realität im Gespräch: Das Strategiemeeting gibt die Richtung, der Lenkungsausschuss reguliert, was zwischen den Einheiten klemmt.">
        @if($canEdit)
            <x-slot name="action">
                <x-nx-button variant="secondary" size="sm" wire:click="openCreate">
                    @svg('heroicon-o-plus', 'w-4 h-4') Gremium
                </x-nx-button>
            </x-slot>
        @endif

        @if($committees->isEmpty())
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-user-group">
                    Noch keine Gremien. Typisch sind ein Strategiemeeting der Geschäftsführung und ein Lenkungsausschuss.
                    @if($canEdit)
                        <x-slot name="action">
                            <x-nx-button size="sm" wire:click="openCreate">Erstes Gremium anlegen</x-nx-button>
                        </x-slot>
                    @endif
                </x-nx-empty>
            </x-nx-card>
        @else
            <x-nx-card flush>
                <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                    @foreach($committees as $committee)
                        @php
                            $next = $nextMeetings[$committee->id] ?? null;
                            $dec = $openDecisions[$committee->id] ?? null;
                        @endphp
                        <li wire:key="committee-{{ $committee->id }}">
                            <x-nx-list-item icon="heroicon-o-user-group" :href="route('management-book.committees.show', $committee)"
                                            :title="$committee->name"
                                            :subtitle="collect([
                                                $committee->chair ? 'Vorsitz ' . $committee->chair->name : null,
                                                $committee->members_count . ' ' . ($committee->members_count === 1 ? 'Mitglied' : 'Mitglieder'),
                                                $committee->cadence,
                                            ])->filter()->implode(' · ')">
                                <x-slot name="trailing">
                                    <div class="flex items-center gap-2">
                                        @if($dec && $dec->n > 0)
                                            <x-nx-badge :variant="$dec->overdue > 0 ? 'danger' : 'warning'">
                                                {{ $dec->n }} offen{{ $dec->overdue > 0 ? ' · ' . $dec->overdue . ' überfällig' : '' }}
                                            </x-nx-badge>
                                        @endif
                                        <span class="hidden text-xs text-[color:var(--nx-muted)] sm:inline">
                                            {{ $next ? 'nächste ' . $next->scheduled_at?->format('d.m.Y') : 'keine Sitzung geplant' }}
                                        </span>
                                        @if($committee->status?->value !== 'active')
                                            <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Book\Committees::statusBadge($committee->status)">{{ $committee->status?->label() }}</x-nx-badge>
                                        @endif
                                        <x-nx-badge :variant="\Platform\ManagementBook\Livewire\Book\Committees::typeBadge($committee->type)">{{ $committee->type?->label() }}</x-nx-badge>
                                    </div>
                                </x-slot>
                            </x-nx-list-item>
                        </li>
                    @endforeach
                </ul>
            </x-nx-card>
        @endif
    </x-nx-section>

    <x-nx-modal wire:model="showCreate" size="md">
        <x-slot name="header">Gremium anlegen</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="form.name" label="Name" wire:model="form.name" placeholder="z. B. Lenkungsausschuss" required />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-nx-input-select name="form.type" label="Typ" :options="$typeOptions" wire:model="form.type" />
                <x-nx-input-text name="form.cadence" label="Rhythmus" wire:model="form.cadence" placeholder="z. B. monatlich" />
            </div>
            <x-nx-input-select name="form.chair_user_id" label="Vorsitz" :options="$userOptions" nullable="true" nullLabel="– offen –" wire:model="form.chair_user_id" />
            <x-nx-input-textarea name="form.description" label="Zweck" wire:model="form.description" rows="3" />
            <p class="m-0 text-xs text-[color:var(--nx-muted)]">Das Gremium enthält keine Ablaufregeln. Agenda und Arbeitsweise legt ihr in den Sitzungen fest.</p>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showCreate', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="create">Anlegen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</div>
