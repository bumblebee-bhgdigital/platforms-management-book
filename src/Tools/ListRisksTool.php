<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\RiskLevel;
use Platform\ManagementBook\Enums\RiskStatus;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookRisk;
use Platform\ManagementBook\Tools\Concerns\HandlesRisks;

class ListRisksTool implements ToolContract, ToolMetadataContract
{
    use HandlesRisks;

    public function getName(): string
    {
        return 'management-book.risks.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/risks - Listet Risiken (Risikomatrix) der sichtbaren Management Books, nach Risikopriorität (Score E × A) absteigend. '
            . 'Einzelnes Risiko: id=<id>. Filter: management_book_id, status, level (low/moderate/high/critical = Ampel), organization_entity_id, category, owner_user_id, search, limit/offset. '
            . 'Mit management_book_id liefert die Antwort zusätzlich die Matrix (Anzahl je E/A-Feld), die Verteilung je Ampelstufe und die Risikobereitschaft des Books.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Optional: einzelnes Risiko anhand der ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'management_book_id' => ['type' => 'integer', 'description' => 'Optional: nur Risiken dieses Management Books (liefert zusätzlich Matrix und Verteilung).'],
                'status' => ['type' => 'string', 'enum' => RiskStatus::values(), 'description' => 'Optional: Status-Filter.'],
                'level' => ['type' => 'string', 'enum' => RiskLevel::values(), 'description' => 'Optional: Ampel-Filter (low 1–4, moderate 5–9, high 10–15, critical 16–25).'],
                'organization_entity_id' => ['type' => 'integer', 'description' => 'Optional: nur Risiken dieses Bereichs/Knotens.'],
                'category' => ['type' => 'string', 'description' => 'Optional: exakte Kategorie.'],
                'owner_user_id' => ['type' => 'integer', 'description' => 'Optional: nach Verantwortlichem filtern.'],
                'search' => ['type' => 'string', 'description' => 'Optional: Suche in Nummer, Bezeichnung, Beschreibung, Maßnahmen.'],
                'limit' => ['type' => 'integer', 'description' => 'Optional: max. Anzahl (Default 100, max 500).'],
                'offset' => ['type' => 'integer', 'description' => 'Optional: Offset.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }

            if (! empty($arguments['id'])) {
                $risk = ManagementBookRisk::query()->where('team_id', $team->id)->find((int) $arguments['id']);
                if (! $risk) {
                    return ToolResult::error('NOT_FOUND', 'Risiko nicht gefunden.');
                }
                if ($denied = $this->authorizeRisk($context, 'view', $risk)) {
                    return $denied;
                }

                return ToolResult::success(['risk' => $this->riskToArray($risk)]);
            }

            $book = null;
            if (! empty($arguments['management_book_id'])) {
                $book = ManagementBook::query()->where('team_id', $team->id)->find((int) $arguments['management_book_id']);
                if (! $book) {
                    return ToolResult::error('NOT_FOUND', 'Management Book nicht gefunden.');
                }
                if ($denied = $this->authorizeBook($context, 'view', $book)) {
                    return $denied;
                }
            }

            $query = ManagementBookRisk::query()->where('team_id', $team->id)
                ->whereIn('management_book_id', ManagementBook::query()->where('team_id', $team->id)->visibleTo($context->user)->select('id'));

            if ($book) {
                $query->where('management_book_id', $book->id);
            }
            if (! empty($arguments['status'])) {
                if (! RiskStatus::tryFrom((string) $arguments['status'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'status muss einer von ' . implode(', ', RiskStatus::values()) . ' sein.');
                }
                $query->where('status', $arguments['status']);
            }
            if (! empty($arguments['level'])) {
                $level = RiskLevel::tryFrom((string) $arguments['level']);
                if (! $level) {
                    return ToolResult::error('VALIDATION_ERROR', 'level muss einer von ' . implode(', ', RiskLevel::values()) . ' sein.');
                }
                $query->whereBetween('score', $this->levelScoreRange($level));
            }
            foreach (['organization_entity_id', 'owner_user_id'] as $col) {
                if (! empty($arguments[$col])) {
                    $query->where($col, (int) $arguments[$col]);
                }
            }
            if (! empty($arguments['category'])) {
                $query->where('category', $arguments['category']);
            }
            if (! empty($arguments['search'])) {
                $term = '%' . $arguments['search'] . '%';
                $query->where(fn ($q) => $q->where('number', 'like', $term)->orWhere('title', 'like', $term)
                    ->orWhere('description', 'like', $term)->orWhere('measures_planned', 'like', $term)
                    ->orWhere('measures_implemented', 'like', $term));
            }

            $total = (clone $query)->count();
            $limit = min(max((int) ($arguments['limit'] ?? 100), 1), 500);
            $offset = max((int) ($arguments['offset'] ?? 0), 0);
            $risks = $query->orderByRaw('score IS NULL')->orderByDesc('score')->orderBy('number')->orderBy('id')
                ->skip($offset)->take($limit)->get();

            $result = [
                'risks' => $risks->map(fn ($r) => $this->riskToArray($r))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ];
            if ($book) {
                $result += $this->bookRiskOverview($book);
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Risiken: ' . $e->getMessage());
        }
    }

    /** Matrix (E × A) und Ampel-Verteilung über alle nicht geschlossenen Risiken des Books. */
    private function bookRiskOverview(ManagementBook $book): array
    {
        $open = ManagementBookRisk::query()->where('management_book_id', $book->id)
            ->where('status', '!=', RiskStatus::CLOSED->value)->get(['probability', 'impact', 'score']);

        $matrix = [];
        for ($p = 5; $p >= 1; $p--) {
            for ($i = 1; $i <= 5; $i++) {
                $matrix[] = [
                    'probability' => $p,
                    'impact' => $i,
                    'score' => $p * $i,
                    'level' => RiskLevel::fromScore($p * $i)->value,
                    'count' => $open->where('probability', $p)->where('impact', $i)->count(),
                ];
            }
        }

        $byLevel = array_fill_keys(RiskLevel::values(), 0);
        foreach ($open as $r) {
            if ($level = RiskLevel::fromScore($r->score)) {
                $byLevel[$level->value]++;
            }
        }

        return [
            'risk_appetite' => $book->risk_appetite?->value,
            'risk_appetite_label' => $book->risk_appetite?->label(),
            'overview' => [
                'scope' => 'alle nicht geschlossenen Risiken des Books',
                'by_level' => $byLevel,
                'unrated' => $open->whereNull('score')->count(),
                'matrix' => $matrix,
            ],
        ];
    }

    public function getMetadata(): array
    {
        return $this->riskMetadata('GET');
    }
}
