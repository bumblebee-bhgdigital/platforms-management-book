{{-- Modals der Sitzungsseite (innerhalb von x-ui-page eingebunden). --}}

<x-nx-modal wire:model="showMeetingEdit" size="md">
    <x-slot name="header">Sitzung bearbeiten</x-slot>
    <div class="space-y-4">
        <x-nx-input-text name="meetingForm.title" label="Titel" wire:model="meetingForm.title" required />
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-nx-input-datetime name="meetingForm.scheduled_at" label="Termin" wire:model="meetingForm.scheduled_at" />
            <x-nx-input-number name="meetingForm.duration_minutes" label="Dauer (Min.)" wire:model="meetingForm.duration_minutes" />
        </div>
        <x-nx-input-text name="meetingForm.location" label="Ort" wire:model="meetingForm.location" />
    </div>
    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            <x-nx-button variant="ghost" wire:click="$set('showMeetingEdit', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveMeeting">Speichern</x-nx-button>
        </div>
    </x-slot>
</x-nx-modal>

<x-nx-modal wire:model="showItem" size="lg">
    <x-slot name="header">{{ $editingItemId ? 'Agenda-Punkt bearbeiten' : 'Agenda-Punkt anlegen' }}</x-slot>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
            <x-nx-input-text name="itemForm.title" label="Titel" wire:model="itemForm.title" required />
            <x-nx-input-select name="itemForm.kind" label="Art" :options="$agendaKindOptions" wire:model.live="itemForm.kind" />
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-nx-input-select name="itemForm.organization_entity_id" label="Einbringende Einheit" :options="$entityOptions" nullable="true" nullLabel="–" wire:model="itemForm.organization_entity_id" />
            <x-nx-input-select name="itemForm.owner_user_id" label="Verantwortlich" :options="$userOptions" nullable="true" nullLabel="–" wire:model="itemForm.owner_user_id" />
            <x-nx-input-number name="itemForm.duration_minutes" label="Dauer (Min.)" wire:model="itemForm.duration_minutes" />
        </div>
        <x-nx-input-textarea name="itemForm.description" label="Beschreibung" wire:model="itemForm.description" rows="2" />

        @if(($itemForm['kind'] ?? null) !== \Platform\ManagementBook\Formats\CommitteeFormat::INFO)
            @php $formFields = $format->agendaFields(); @endphp
            <div class="rounded-[8px] border border-[color:var(--nx-line)] p-4">
                <div class="mb-3 text-sm font-medium">{{ $format->texts()['guide_title'] }}</div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach($formFields as $field => $f)
                        <div class="{{ empty($f['aside']) ? 'sm:col-span-2' : '' }}">
                            <x-nx-input-textarea :name="'itemForm.' . $field" :label="$f['no'] . '. ' . $f['label']" wire:model="itemForm.{{ $field }}" rows="2" :placeholder="$f['placeholder']" />
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <p class="m-0 text-sm text-[color:var(--nx-muted)]">Informationspunkte haben keinen Leitfaden und zählen nicht in die Erfolgsmessung.</p>
        @endif
    </div>
    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            <x-nx-button variant="ghost" wire:click="$set('showItem', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveItem">Speichern</x-nx-button>
        </div>
    </x-slot>
</x-nx-modal>

<x-nx-modal wire:model="showDecision" size="md">
    <x-slot name="header">{{ $editingDecisionId ? 'Beschluss bearbeiten' : 'Beschluss festhalten' }}</x-slot>
    <div class="space-y-4">
        <x-nx-input-textarea name="decisionForm.text" label="Beschluss" wire:model="decisionForm.text" rows="3" required />
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-nx-input-select name="decisionForm.kind" label="Art" :options="$decisionKindOptions" wire:model="decisionForm.kind" />
            <x-nx-input-select name="decisionForm.status" label="Status" :options="$decisionStatusOptions" wire:model="decisionForm.status" />
            <x-nx-input-date name="decisionForm.due_date" label="Fällig" wire:model="decisionForm.due_date" />
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-nx-input-select name="decisionForm.owner_user_id" label="Verantwortlich" :options="$userOptions" nullable="true" nullLabel="–" wire:model="decisionForm.owner_user_id" />
            <x-nx-input-select name="decisionForm.agenda_item_id" label="Agenda-Punkt" :options="$itemOptions" nullable="true" nullLabel="–" wire:model="decisionForm.agenda_item_id" />
        </div>
    </div>
    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            <x-nx-button variant="ghost" wire:click="$set('showDecision', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveDecision">Speichern</x-nx-button>
        </div>
    </x-slot>
</x-nx-modal>

<x-nx-modal wire:model="showAttendee" size="sm">
    <x-slot name="header">Teilnehmer hinzufügen</x-slot>
    <div class="space-y-4">
        <x-nx-input-select name="attendeeForm.user_id" label="Person oder Agent" :options="$userOptions" nullable="true" nullLabel="– wählen –" wire:model="attendeeForm.user_id" required />
        <x-nx-input-select name="attendeeForm.role" label="Rolle" :options="array_map(fn ($r) => ['value' => $r->value, 'label' => $r->label()], \Platform\ManagementBook\Enums\CommitteeMemberRole::cases())" wire:model="attendeeForm.role" />
    </div>
    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            <x-nx-button variant="ghost" wire:click="$set('showAttendee', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="addAttendee">Hinzufügen</x-nx-button>
        </div>
    </x-slot>
</x-nx-modal>

<x-nx-modal wire:model="showMinutes" size="lg">
    <x-slot name="header">Protokoll</x-slot>
    <x-nx-input-textarea name="minutesForm.content" label="Inhalt (Markdown)" wire:model="minutesForm.content" rows="16" />
    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            <x-nx-button variant="ghost" wire:click="$set('showMinutes', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveMinutes">Speichern</x-nx-button>
        </div>
    </x-slot>
</x-nx-modal>

<x-nx-modal wire:model="showDocument" size="sm">
    <x-slot name="header">Unterlage hinzufügen</x-slot>
    <div class="space-y-4">
        <x-nx-input-text name="documentForm.title" label="Titel" wire:model="documentForm.title" required />
        <x-nx-input-text name="documentForm.type" label="Art" wire:model="documentForm.type" placeholder="z. B. Vorlage, Präsentation, Bericht" />
        <x-nx-input-select name="documentForm.agenda_item_id" label="Agenda-Punkt" :options="$itemOptions" nullable="true" nullLabel="–" wire:model="documentForm.agenda_item_id" />
        <p class="m-0 text-xs text-[color:var(--nx-muted)]">Dateien werden über den Datei-Upload der Plattform hochgeladen und mit der Unterlage verknüpft.</p>
    </div>
    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            <x-nx-button variant="ghost" wire:click="$set('showDocument', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveDocument">Hinzufügen</x-nx-button>
        </div>
    </x-slot>
</x-nx-modal>
