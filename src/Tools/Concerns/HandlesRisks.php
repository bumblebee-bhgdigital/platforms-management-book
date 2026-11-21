<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\RiskLevel;
use Platform\ManagementBook\Enums\RiskStatus;
use Platform\ManagementBook\Models\ManagementBookRisk;
use Platform\Organization\Models\OrganizationEntity;

/** Gemeinsame Helfer der management-book.risks.*-Tools (Schema, Validierung, Serialisierung). */
trait HandlesRisks
{
    use HandlesManagementBooks;

    /** Frei schreibbare Felder (POST/PUT). */
    protected function riskWritableFields(): array
    {
        return [
            'number', 'title', 'description', 'organization_entity_id', 'probability', 'impact', 'category',
            'measures_planned', 'measures_implemented', 'owner_user_id', 'owner_agent_id', 'due_date', 'status', 'review_date',
        ];
    }

    protected function authorizeRisk(ToolContext $context, string $ability, ManagementBookRisk $risk): ?ToolResult
    {
        return Gate::forUser($context->user)->allows($ability, $risk)
            ? null
            : ToolResult::error('ACCESS_DENIED', "Keine Berechtigung ({$ability}) für Risiko #{$risk->id}.");
    }

    /** Gemeinsame Property-Definitionen für POST/PUT. */
    protected function riskFieldSchema(): array
    {
        return [
            'number' => ['type' => 'string', 'description' => 'Risikonummer (frei, z. B. "R-001").'],
            'title' => ['type' => 'string', 'description' => 'Risikobezeichnung.'],
            'description' => ['type' => 'string', 'description' => 'Beschreibung (Markdown).'],
            'organization_entity_id' => ['type' => 'integer', 'description' => 'Bereich/Abteilung als Knoten der Organisation (Entity-ID, lose gekoppelt). Nutze "organization.entities.GET". null löst die Zuordnung.'],
            'probability' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'description' => 'Eintrittswahrscheinlichkeit E: 1 sehr unwahrscheinlich · 2 unwahrscheinlich · 3 möglich · 4 wahrscheinlich · 5 sehr wahrscheinlich.'],
            'impact' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'description' => 'Auswirkung A: 1 gering · 2 moderat · 3 deutlich · 4 schwerwiegend · 5 existenzbedrohend.'],
            'category' => ['type' => 'string', 'description' => 'Risikokategorie (frei, z. B. "operatives Risiko").'],
            'measures_planned' => ['type' => 'string', 'description' => 'Geplante Maßnahmen (Markdown).'],
            'measures_implemented' => ['type' => 'string', 'description' => 'Umgesetzte Maßnahmen (Markdown).'],
            'owner_user_id' => ['type' => 'integer', 'description' => 'Verantwortlich (User-ID). Entweder owner_user_id oder owner_agent_id.'],
            'owner_agent_id' => ['type' => 'integer', 'description' => 'Verantwortlicher Agent (User-ID eines Agent-Users).'],
            'due_date' => ['type' => 'string', 'description' => 'Frist (YYYY-MM-DD).'],
            'status' => ['type' => 'string', 'enum' => RiskStatus::values(), 'description' => 'Status: open (offen, Default), monitored (beobachtet), treated (behandelt), closed (geschlossen).'],
            'review_date' => ['type' => 'string', 'description' => 'Nächste Überprüfung (YYYY-MM-DD).'],
        ];
    }

    /** Prüft die Eingaben; liefert eine Fehlermeldung oder null. */
    protected function validateRiskInput(array $arguments, ?ManagementBookRisk $existing = null): ?string
    {
        foreach (['probability' => 'Eintrittswahrscheinlichkeit', 'impact' => 'Auswirkung'] as $col => $label) {
            $v = $arguments[$col] ?? null;
            if ($v !== null && $v !== '' && (! is_numeric($v) || (int) $v != $v || $v < 1 || $v > 5)) {
                return "{$col} ({$label}) muss eine ganze Zahl von 1 bis 5 sein.";
            }
        }
        if (! empty($arguments['status']) && ! RiskStatus::tryFrom((string) $arguments['status'])) {
            return 'status muss einer von ' . implode(', ', RiskStatus::values()) . ' sein.';
        }
        foreach (['due_date', 'review_date'] as $col) {
            if (! empty($arguments[$col])) {
                try {
                    Carbon::parse((string) $arguments[$col]);
                } catch (\Throwable) {
                    return "{$col} ist kein gültiges Datum (YYYY-MM-DD).";
                }
            }
        }

        $ownerUser = array_key_exists('owner_user_id', $arguments) ? $arguments['owner_user_id'] : $existing?->owner_user_id;
        $ownerAgent = array_key_exists('owner_agent_id', $arguments) ? $arguments['owner_agent_id'] : $existing?->owner_agent_id;
        if (! empty($ownerUser) && ! empty($ownerAgent)) {
            return 'Genau eines von owner_user_id oder owner_agent_id angeben.';
        }
        $userIds = array_values(array_unique(array_map('intval', array_filter([$arguments['owner_user_id'] ?? null, $arguments['owner_agent_id'] ?? null]))));
        if ($userIds !== []) {
            $known = \Platform\Core\Models\User::whereIn('id', $userIds)->pluck('id')->map(fn ($i) => (int) $i)->all();
            if ($missing = array_diff($userIds, $known)) {
                return 'Unbekannte User-ID(s): ' . implode(', ', $missing) . '.';
            }
        }

        if (! empty($arguments['organization_entity_id']) && ! OrganizationEntity::whereKey((int) $arguments['organization_entity_id'])->exists()) {
            return "Knoten (organization_entity_id) {$arguments['organization_entity_id']} nicht gefunden.";
        }

        return null;
    }

    /** Nimmt nur schreibbare Felder und normalisiert Leerwerte/Daten. */
    protected function riskFields(array $arguments): array
    {
        $fields = array_intersect_key($arguments, array_flip($this->riskWritableFields()));
        foreach ($fields as $key => $value) {
            if ($value === '') {
                $fields[$key] = null;
            }
        }
        foreach (['due_date', 'review_date'] as $col) {
            if (! empty($fields[$col])) {
                $fields[$col] = Carbon::parse((string) $fields[$col])->toDateString();
            }
        }
        if (isset($fields['title'])) {
            $fields['title'] = trim((string) $fields['title']);
        }

        return $fields;
    }

    /** @return array<string,mixed> */
    protected function riskToArray(ManagementBookRisk $r): array
    {
        $level = $r->level();
        $r->loadMissing('entity:id,name');

        return [
            'id' => $r->id,
            'uuid' => $r->uuid,
            'team_id' => $r->team_id,
            'management_book_id' => $r->management_book_id,
            'number' => $r->number,
            'title' => $r->title,
            'description' => $r->description,
            'organization_entity_id' => $r->organization_entity_id,
            'entity_name' => $r->entity?->name,
            'probability' => $r->probability,
            'impact' => $r->impact,
            'score' => $r->score,
            'level' => $level?->value,
            'level_label' => $level?->label(),
            'level_color' => $level?->color(),
            'level_guidance' => $level?->guidance(),
            'category' => $r->category,
            'measures_planned' => $r->measures_planned,
            'measures_implemented' => $r->measures_implemented,
            'owner_user_id' => $r->owner_user_id,
            'owner_agent_id' => $r->owner_agent_id,
            'due_date' => $r->due_date?->toDateString(),
            'status' => $r->status?->value,
            'status_label' => $r->status?->label(),
            'review_date' => $r->review_date?->toDateString(),
            'created_by' => $r->created_by,
            'created_at' => $r->created_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];
    }

    /** Score-Bereich je Ampelstufe (für den level-Filter). */
    protected function levelScoreRange(RiskLevel $level): array
    {
        return match ($level) {
            RiskLevel::LOW => [1, 4],
            RiskLevel::MODERATE => [5, 9],
            RiskLevel::HIGH => [10, 15],
            RiskLevel::CRITICAL => [16, 25],
        };
    }

    protected function riskMetadata(string $verb): array
    {
        return [
            'category' => $verb === 'GET' ? 'query' : 'action',
            'tags' => ['management-book', 'risks', strtolower($verb)],
            'read_only' => $verb === 'GET',
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => match ($verb) {
                'GET' => 'safe',
                'DELETE' => 'destructive',
                default => 'write',
            },
            'idempotent' => in_array($verb, ['GET', 'PUT'], true),
        ];
    }
}
