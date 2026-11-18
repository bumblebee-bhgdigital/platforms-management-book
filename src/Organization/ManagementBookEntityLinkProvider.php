<?php

namespace Platform\ManagementBook\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\ManagementBook\Enums\ManagementBookStatus;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\Organization\Contracts\EntityLinkProvider;

/**
 * Macht das Management Book im Organisations-Modul an Knoten (Entities) verknüpfbar —
 * DimensionLink/Bridge analog PlannerEntityLinkProvider (Alias 'project' -> hier 'management_book').
 *
 * Registrierung: ManagementBookServiceProvider::boot() (EntityLinkRegistry::register).
 * Damit ist der Typ für organization.entity.link.POST (object_type "management_book" oder Kurzname "book"),
 * entity.detail/summary und die Terminal-App "Verknüpfungen" adressierbar.
 *
 * Bewusst NICHT implementiert (Book hat noch keine Kennzahlen/Personenbezug): HasMetricDefinitions,
 * HasPersonMetrics, HasContextParent (kommt mit den Gremien — Gremium/Sitzung erben die Verortung vom Book).
 */
class ManagementBookEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return [ManagementBook::MORPH_ALIAS];
    }

    public function linkTypeConfig(): array
    {
        return [
            ManagementBook::MORPH_ALIAS => [
                'label' => 'Management Books',
                'singular' => 'Management Book',
                'icon' => 'book-open',
                'route' => 'management-book.books.show',
            ],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        if ($morphAlias === ManagementBook::MORPH_ALIAS) {
            $query->with(['owner:id,name', 'responsibleAgent:id,name']);
        }
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        if ($morphAlias !== ManagementBook::MORPH_ALIAS) {
            return [];
        }

        return [
            'status' => $model->status?->label(),
            'is_active' => $model->status === ManagementBookStatus::ACTIVE,
            'responsible' => $model->owner?->name,
            'agent' => $model->responsibleAgent?->name,
            'horizon_year' => $model->horizon_year ? (string) $model->horizon_year : null,
            'valid_from' => $model->valid_from?->format('d.m.Y'),
            'valid_until' => $model->valid_until?->format('d.m.Y'),
            'next_review_at' => $model->next_review_at?->format('d.m.Y'),
        ];
    }

    public function metadataDisplayRules(): array
    {
        return [
            ManagementBook::MORPH_ALIAS => [
                ['field' => 'status', 'format' => 'badge'],
                ['field' => 'responsible', 'format' => 'text'],
                ['field' => 'horizon_year', 'format' => 'prefixed_text', 'prefix' => 'Horizont '],
                ['field' => 'next_review_at', 'format' => 'prefixed_text', 'prefix' => 'Review '],
            ],
        ];
    }

    /** Keine Zeiterfassung am Book. */
    public function timeTrackableCascades(): array
    {
        return [];
    }

    /** Noch keine Kennzahlen (folgt mit Strategie/Risiken). */
    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        return [];
    }

    /** Noch keine Kind-Objekte für den Activity-Feed (Gremien/Sitzungen folgen). */
    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }
}
