{{-- Dialoge des Strategie-Bereichs (Fokusraum, Element, Forecast, Forecast-Version). --}}
@php $kindCfg = $kinds[$itemKind] ?? $kinds['milestone']; @endphp

<x-nx-modal wire:model="showFocusArea" size="lg">
    <x-slot name="header">{{ $editingFocusAreaId ? 'Fokusraum bearbeiten' : 'Fokusraum anlegen' }}</x-slot>
    <div class="space-y-4">
        <x-nx-input-text name="faForm.title" label="Titel" wire:model="faForm.title" required placeholder="z. B. Eventgeschäft ausbauen" />
        <x-nx-input-textarea name="faForm.description" label="Beschreibung" wire:model="faForm.description" rows="3" />
        <x-nx-input-select name="faForm.forecast_id" label="Regnose / Forecast" :options="$forecastOptions" nullable="true" nullLabel="– keine Zuordnung –" wire:model="faForm.forecast_id" />
        <div>
            <div class="mb-2 text-xs font-medium text-[color:var(--nx-text)]">Leitfragen der drei Spalten <span class="font-normal text-[color:var(--nx-muted)]">(leer = Standard-Leitfrage)</span></div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <x-nx-input-textarea name="faForm.central_question_vision_images" label="Zielbilder" wire:model="faForm.central_question_vision_images" rows="2" :placeholder="$kinds['vision_image']['default_question']" />
                <x-nx-input-textarea name="faForm.central_question_obstacles" label="Hindernisse" wire:model="faForm.central_question_obstacles" rows="2" :placeholder="$kinds['obstacle']['default_question']" />
                <x-nx-input-textarea name="faForm.central_question_milestones" label="Meilensteine" wire:model="faForm.central_question_milestones" rows="2" :placeholder="$kinds['milestone']['default_question']" />
            </div>
        </div>
    </div>
    <x-slot name="footer">
        <x-nx-button variant="ghost" wire:click="$set('showFocusArea', false)">Abbrechen</x-nx-button>
        <x-nx-button variant="primary" wire:click="saveFocusArea">Speichern</x-nx-button>
    </x-slot>
</x-nx-modal>

<x-nx-modal wire:model="showItem" size="md">
    <x-slot name="header">{{ $kindCfg['label'] }} {{ $editingItemId ? 'bearbeiten' : 'hinzufügen' }}</x-slot>
    <div class="space-y-4">
        <x-nx-input-text name="itemForm.title" label="Titel" wire:model="itemForm.title" required />
        <x-nx-input-textarea name="itemForm.description" label="Beschreibung" wire:model="itemForm.description" rows="3" />
        <x-nx-input-select name="itemForm.focus_area_id" label="Fokusraum" :options="$focusAreaOptions" wire:model="itemForm.focus_area_id" />
        @if($itemKind === 'milestone')
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <x-nx-input-number name="itemForm.target_year" label="Zieljahr" wire:model="itemForm.target_year" />
                <x-nx-input-select name="itemForm.target_quarter" label="Quartal" :options="$quarterOptions" nullable="true" nullLabel="ganzes Jahr" wire:model="itemForm.target_quarter" />
                <x-nx-input-date name="itemForm.target_date" label="Datum (optional)" wire:model="itemForm.target_date" />
            </div>
            <x-nx-input-select name="itemForm.status" label="Status" :options="$statusOptions" wire:model="itemForm.status" />
        @endif
        <x-nx-input-text name="itemForm.central_question" label="Eigene Leitfrage (optional)" wire:model="itemForm.central_question" />
    </div>
    <x-slot name="footer">
        @if($editingItemId)
            <x-nx-button variant="danger" class="mr-auto" wire:click="deleteItem('{{ $itemKind }}', {{ $editingItemId }})" wire:confirm="{{ $kindCfg['label'] }} löschen?">Löschen</x-nx-button>
        @endif
        <x-nx-button variant="ghost" wire:click="$set('showItem', false)">Abbrechen</x-nx-button>
        <x-nx-button variant="primary" wire:click="saveItem">Speichern</x-nx-button>
    </x-slot>
</x-nx-modal>

<x-nx-modal wire:model="showForecast" size="md">
    <x-slot name="header">{{ $editingForecastId ? 'Forecast bearbeiten' : 'Regnose / Forecast anlegen' }}</x-slot>
    <div class="space-y-4">
        <x-nx-input-text name="forecastForm.title" label="Titel" wire:model="forecastForm.title" required placeholder="z. B. Regnose 2030" />
        <x-nx-input-number name="forecastForm.target_year" label="Zieljahr" wire:model="forecastForm.target_year" />
        @unless($editingForecastId)
            <x-nx-input-textarea name="forecastForm.content" label="Zukunftsbild (Markdown, wird Version 1)" wire:model="forecastForm.content" rows="8"
                                 placeholder="Was sieht man im Zieljahr? Woran erkennt ein Außenstehender, dass wir angekommen sind?" />
        @else
            <p class="m-0 text-xs text-[color:var(--nx-muted)]">Den Inhalt änderst du über „Neue Version“ – so bleibt die Historie erhalten.</p>
        @endunless
    </div>
    <x-slot name="footer">
        <x-nx-button variant="ghost" wire:click="$set('showForecast', false)">Abbrechen</x-nx-button>
        <x-nx-button variant="primary" wire:click="saveForecast">Speichern</x-nx-button>
    </x-slot>
</x-nx-modal>

<x-nx-modal wire:model="showVersion" size="lg">
    <x-slot name="header">Neue Version der Regnose</x-slot>
    <div class="space-y-4">
        <x-nx-input-textarea name="versionForm.content" label="Zukunftsbild (Markdown)" wire:model="versionForm.content" rows="12" required />
        <x-nx-input-text name="versionForm.change_note" label="Was hat sich geändert? (optional)" wire:model="versionForm.change_note" />
    </div>
    <x-slot name="footer">
        <x-nx-button variant="ghost" wire:click="$set('showVersion', false)">Abbrechen</x-nx-button>
        <x-nx-button variant="primary" wire:click="saveVersion">Version speichern</x-nx-button>
    </x-slot>
</x-nx-modal>
