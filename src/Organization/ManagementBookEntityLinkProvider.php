<?php

namespace Platform\ManagementBook\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\ManagementBook\Enums\CommitteeType;
use Platform\ManagementBook\Enums\DecisionStatus;
use Platform\ManagementBook\Enums\ManagementBookStatus;
use Platform\ManagementBook\Enums\MeetingStatus;
use Platform\ManagementBook\Enums\MilestoneStatus;
use Platform\ManagementBook\Enums\RiskLevel;
use Platform\ManagementBook\Enums\RiskStatus;
use Platform\ManagementBook\Formats\CommitteeFormat;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookAgendaItem;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookDecision;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\ManagementBook\Models\ManagementBookRisk;
use Platform\ManagementBook\Services\BookInsights;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;

/**
 * Macht das Management Book im Organisations-Modul an Knoten (Entities) verknüpfbar —
 * DimensionLink/Bridge analog PlannerEntityLinkProvider (Alias 'project' -> hier 'management_book').
 *
 * Registrierung: ManagementBookServiceProvider::boot() (EntityLinkRegistry::register).
 * Damit ist der Typ für organization.entity.link.POST (object_type "management_book" oder Kurzname "book"),
 * entity.detail/summary und die Terminal-App "Verknüpfungen" adressierbar.
 *
 * Kennzahlen (#1060): metrics() meldet je Knoten Risiken, Meilensteine, Fokusräume, Beschlüsse und Sitzungen
 * aller verknüpften Books (Entwurf und aktiv) an die Organisation; metricDefinitions() beschreibt sie (Gruppe
 * „management“). Die Organisation speichert sie im nächtlichen Snapshot und zeigt sie am Knoten.
 *
 * Gremien (Alias management_book_committee) hängen ebenfalls an Knoten – typischerweise an den Gremien-Knoten
 * der Organisation (z. B. „BHG.DIGITAL Lenkungsausschuss“). Sie melden Sitzungen, Beschlüsse und – nur beim
 * Lenkungsausschuss – die Handlungsfähigkeit der letzten Sitzung (Kennzahlen mbc_*).
 * Handlungsfähigkeit zählt überall nur LA-Sitzungen; Strategiemeeting und Board haben eigene Erfolgsmaße.
 *
 * Bewusst NICHT implementiert: HasPersonMetrics, HasContextParent.
 */
class ManagementBookEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions
{
    public function morphAliases(): array
    {
        return [ManagementBook::MORPH_ALIAS, ManagementBookCommittee::MORPH_ALIAS];
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
            ManagementBookCommittee::MORPH_ALIAS => [
                'label' => 'Gremien',
                'singular' => 'Gremium',
                'icon' => 'user-group',
                'route' => 'management-book.committees.show',
            ],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        if ($morphAlias === ManagementBook::MORPH_ALIAS) {
            $query->with(['owner:id,name', 'responsibleAgent:id,name']);
        }
        if ($morphAlias === ManagementBookCommittee::MORPH_ALIAS) {
            $query->with(['book:id,title', 'chair:id,name']);
        }
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        if ($morphAlias === ManagementBookCommittee::MORPH_ALIAS) {
            return [
                'type' => $model->type?->label(),
                'format' => $model->format()->name(),
                'status' => $model->status?->label(),
                'book' => $model->book?->title,
                'chair' => $model->chair?->name,
                'cadence' => $model->cadence,
            ];
        }
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
            ManagementBookCommittee::MORPH_ALIAS => [
                ['field' => 'type', 'format' => 'badge'],
                ['field' => 'book', 'format' => 'text'],
                ['field' => 'chair', 'format' => 'prefixed_text', 'prefix' => 'Vorsitz '],
                ['field' => 'cadence', 'format' => 'text'],
            ],
        ];
    }

    /** Keine Zeiterfassung am Book. */
    public function timeTrackableCascades(): array
    {
        return [];
    }

    /**
     * Kennzahlen je Knoten über alle verknüpften, laufenden Books (Entwurf/aktiv; abgelöste und archivierte nicht).
     * Gebündelt: eine Abfrage je Tabelle für alle Books, keine Abfragen pro Book.
     */
    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        if ($morphAlias === ManagementBookCommittee::MORPH_ALIAS) {
            return $this->committeeMetrics($linksByEntity);
        }
        if ($morphAlias !== ManagementBook::MORPH_ALIAS) {
            return [];
        }

        $allIds = array_values(array_unique(array_merge(...array_values($linksByEntity ?: [[]]))));
        if ($allIds === []) {
            return [];
        }

        $books = ManagementBook::query()->whereIn('id', $allIds)
            ->whereIn('status', [ManagementBookStatus::DRAFT->value, ManagementBookStatus::ACTIVE->value])
            ->get(['id', 'status', 'next_review_at'])->keyBy('id');
        if ($books->isEmpty()) {
            return [];
        }
        $bookIds = $books->keys()->all();

        $risks = ManagementBookRisk::query()->whereIn('management_book_id', $bookIds)
            ->where('status', '!=', RiskStatus::CLOSED->value)
            ->get(['id', 'management_book_id', 'score'])->groupBy('management_book_id');
        $milestones = ManagementBookMilestone::query()->whereIn('management_book_id', $bookIds)
            ->get(['id', 'management_book_id', 'status', 'target_date', 'target_year', 'target_quarter'])->groupBy('management_book_id');
        $focusAreas = ManagementBookFocusArea::query()->whereIn('management_book_id', $bookIds)
            ->selectRaw('management_book_id, count(*) as n')->groupBy('management_book_id')->pluck('n', 'management_book_id');

        $committeeRows = ManagementBookCommittee::query()->whereIn('management_book_id', $bookIds)->get(['id', 'management_book_id', 'type']);
        $committeeBook = $committeeRows->pluck('management_book_id', 'id');
        $steeringIds = $committeeRows->filter(fn ($c) => $c->type === CommitteeType::STEERING_COMMITTEE)->pluck('id')->all();
        $meetings = $committeeBook->isEmpty() ? collect() : ManagementBookMeeting::query()
            ->whereIn('committee_id', $committeeBook->keys())->get(['id', 'committee_id', 'status', 'scheduled_at']);
        $bookOfMeeting = $meetings->mapWithKeys(fn ($m) => [$m->id => $committeeBook[$m->committee_id] ?? null]);
        $meetingsByBook = $meetings->groupBy(fn ($m) => $bookOfMeeting[$m->id]);

        $decisionsByBook = $meetings->isEmpty() ? collect() : ManagementBookDecision::query()
            ->whereIn('meeting_id', $meetings->pluck('id'))->where('status', DecisionStatus::OPEN->value)
            ->get(['id', 'meeting_id', 'due_date'])->groupBy(fn ($d) => $bookOfMeeting[$d->meeting_id] ?? 0);

        // Handlungsfähigkeit der jeweils letzten gehaltenen LA-Sitzung je Book (Kriterien wie in der Sitzungsansicht).
        // Strategiemeeting und Board messen etwas anderes und zählen hier nicht.
        $lastHeld = $meetingsByBook->map(fn ($ms) => $ms->filter(fn ($m) => $m->status === MeetingStatus::HELD && $m->scheduled_at
            && in_array($m->committee_id, $steeringIds, true))->sortByDesc('scheduled_at')->first())->filter();
        $capability = $this->capabilityOf($lastHeld);

        $today = today();
        $monthAgo = now()->subDays(30);

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $m = [
                'mb_books' => 0, 'mb_reviews_due' => 0,
                'mb_risks_open' => 0, 'mb_risks_hot' => 0, 'mb_risks_critical' => 0, 'mb_risks_unrated' => 0,
                'mb_focus_areas' => 0,
                'mb_milestones_total' => 0, 'mb_milestones_reached' => 0, 'mb_milestones_overdue' => 0,
                'mb_decisions_open' => 0, 'mb_decisions_overdue' => 0,
                'mb_meetings_held_30d' => 0,
            ];
            $maxScore = null;
            $nextMeeting = null;
            $capabilities = [];

            foreach (array_unique($ids) as $id) {
                $book = $books[$id] ?? null;
                if (! $book) {
                    continue;
                }
                $m['mb_books']++;
                if ($book->next_review_at && $book->next_review_at->lte($today)) {
                    $m['mb_reviews_due']++;
                }

                foreach ($risks[$id] ?? [] as $r) {
                    $m['mb_risks_open']++;
                    $level = RiskLevel::fromScore($r->score);
                    if ($level === null) {
                        $m['mb_risks_unrated']++;
                        continue;
                    }
                    if (in_array($level, [RiskLevel::HIGH, RiskLevel::CRITICAL], true)) {
                        $m['mb_risks_hot']++;
                    }
                    if ($level === RiskLevel::CRITICAL) {
                        $m['mb_risks_critical']++;
                    }
                    $maxScore = max($maxScore ?? 0, (int) $r->score);
                }

                $m['mb_focus_areas'] += (int) ($focusAreas[$id] ?? 0);

                foreach ($milestones[$id] ?? [] as $ms) {
                    if ($ms->status === MilestoneStatus::CANCELLED) {
                        continue;
                    }
                    $m['mb_milestones_total']++;
                    if ($ms->status === MilestoneStatus::REACHED) {
                        $m['mb_milestones_reached']++;
                    } elseif (BookInsights::milestoneIsOverdue($ms)) {
                        $m['mb_milestones_overdue']++;
                    }
                }

                foreach ($decisionsByBook[$id] ?? [] as $d) {
                    $m['mb_decisions_open']++;
                    if ($d->due_date && $d->due_date->lt($today)) {
                        $m['mb_decisions_overdue']++;
                    }
                }

                foreach ($meetingsByBook[$id] ?? [] as $mt) {
                    if (! $mt->scheduled_at) {
                        continue;
                    }
                    if ($mt->status === MeetingStatus::HELD && $mt->scheduled_at->gte($monthAgo)) {
                        $m['mb_meetings_held_30d']++;
                    }
                    if ($mt->scheduled_at->gte(now()) && ! in_array($mt->status, [MeetingStatus::CANCELLED, MeetingStatus::HELD], true)
                        && ($nextMeeting === null || $mt->scheduled_at->lt($nextMeeting))) {
                        $nextMeeting = $mt->scheduled_at;
                    }
                }

                if (isset($capability[$id])) {
                    $capabilities[] = $capability[$id];
                }
            }

            if ($m['mb_books'] === 0) {
                continue;
            }

            $m['mb_risk_score_max'] = $maxScore;
            $m['mb_milestones_reached_percent'] = $m['mb_milestones_total'] > 0
                ? (int) round($m['mb_milestones_reached'] / $m['mb_milestones_total'] * 100) : null;
            $m['mb_days_to_next_meeting'] = $nextMeeting ? (int) $today->diffInDays($nextMeeting->copy()->startOfDay()) : null;
            $m['mb_meeting_capability_percent'] = $capabilities ? (int) round(array_sum($capabilities) / count($capabilities)) : null;

            $result[$entityId] = array_filter($m, fn ($v) => $v !== null);
        }

        return $result;
    }

    /**
     * Handlungsfähigkeit (0–100) je Schlüssel für die übergebenen LA-Sitzungen.
     *
     * @param \Illuminate\Support\Collection<int|string, ManagementBookMeeting> $meetings Schlüssel => Sitzung
     * @return array<int|string, float>
     */
    private function capabilityOf(\Illuminate\Support\Collection $meetings): array
    {
        if ($meetings->isEmpty()) {
            return [];
        }
        $format = CommitteeFormat::for(CommitteeType::STEERING_COMMITTEE);
        $items = ManagementBookAgendaItem::query()->whereIn('meeting_id', $meetings->pluck('id'))
            ->get(['id', 'meeting_id', 'kind', 'goal', 'observation', 'obstacle', 'local_solution', 'system_support', 'outcome_checks'])
            ->groupBy('meeting_id');
        $out = [];
        foreach ($meetings as $key => $meeting) {
            $a = $format->assess($items[$meeting->id] ?? []);
            if ($a['max'] > 0) {
                $out[$key] = $a['done'] / $a['max'] * 100;
            }
        }

        return $out;
    }

    /**
     * Kennzahlen je Knoten über die dort verorteten Gremien (aktiv oder ruhend; aufgelöste zählen nicht).
     *
     * @param array<int, int[]> $linksByEntity
     */
    private function committeeMetrics(array $linksByEntity): array
    {
        $allIds = array_values(array_unique(array_merge(...array_values($linksByEntity ?: [[]]))));
        if ($allIds === []) {
            return [];
        }
        $committees = ManagementBookCommittee::query()->whereIn('id', $allIds)
            ->where('status', '!=', \Platform\ManagementBook\Enums\CommitteeStatus::DISSOLVED->value)
            ->get(['id', 'type'])->keyBy('id');
        if ($committees->isEmpty()) {
            return [];
        }

        $meetings = ManagementBookMeeting::query()->whereIn('committee_id', $committees->keys())
            ->get(['id', 'committee_id', 'status', 'scheduled_at'])->groupBy('committee_id');
        $meetingCommittee = $meetings->flatten()->pluck('committee_id', 'id');
        $decisions = $meetingCommittee->isEmpty() ? collect() : ManagementBookDecision::query()
            ->whereIn('meeting_id', $meetingCommittee->keys())->where('status', DecisionStatus::OPEN->value)
            ->get(['id', 'meeting_id', 'due_date'])->groupBy(fn ($d) => $meetingCommittee[$d->meeting_id] ?? 0);

        $lastHeldLa = $committees->filter(fn ($c) => $c->type === CommitteeType::STEERING_COMMITTEE)
            ->map(fn ($c) => ($meetings[$c->id] ?? collect())->filter(fn ($m) => $m->status === MeetingStatus::HELD && $m->scheduled_at)
                ->sortByDesc('scheduled_at')->first())->filter();
        $capability = $this->capabilityOf($lastHeldLa);

        $today = today();
        $monthAgo = now()->subDays(30);
        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $m = ['mbc_committees' => 0, 'mbc_meetings_held_30d' => 0, 'mbc_decisions_open' => 0, 'mbc_decisions_overdue' => 0];
            $next = null;
            $caps = [];
            foreach (array_unique($ids) as $id) {
                if (! isset($committees[$id])) {
                    continue;
                }
                $m['mbc_committees']++;
                foreach ($meetings[$id] ?? [] as $mt) {
                    if (! $mt->scheduled_at) {
                        continue;
                    }
                    if ($mt->status === MeetingStatus::HELD && $mt->scheduled_at->gte($monthAgo)) {
                        $m['mbc_meetings_held_30d']++;
                    }
                    if ($mt->scheduled_at->gte(now()) && ! in_array($mt->status, [MeetingStatus::CANCELLED, MeetingStatus::HELD], true)
                        && ($next === null || $mt->scheduled_at->lt($next))) {
                        $next = $mt->scheduled_at;
                    }
                }
                foreach ($decisions[$id] ?? [] as $d) {
                    $m['mbc_decisions_open']++;
                    if ($d->due_date && $d->due_date->lt($today)) {
                        $m['mbc_decisions_overdue']++;
                    }
                }
                if (isset($capability[$id])) {
                    $caps[] = $capability[$id];
                }
            }
            if ($m['mbc_committees'] === 0) {
                continue;
            }
            $m['mbc_days_to_next_meeting'] = $next ? (int) $today->diffInDays($next->copy()->startOfDay()) : null;
            $m['mbc_meeting_capability_percent'] = $caps ? (int) round(array_sum($caps) / count($caps)) : null;
            $result[$entityId] = array_filter($m, fn ($v) => $v !== null);
        }

        return $result;
    }

    /**
     * Beschreibung der Kennzahlen für Snapshots, Kennzahlen-Ansicht und Dimensionen der Organisation.
     * Keine Metrik ist is_dimension_primary – die kanonischen Maße der Dimensionen bleiben bei Planner & Co.
     */
    public function metricDefinitions(): array
    {
        $g = 'management';

        return [
            'mb_books' => ['label' => 'Management Books', 'group' => $g, 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'mb_reviews_due' => ['label' => 'Book-Review fällig', 'group' => $g, 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'pair' => 'mb_books'],

            'mb_risks_open' => ['label' => 'Offene Risiken', 'group' => $g, 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'mb_risks_hot' => ['label' => 'Risiken hoch/kritisch', 'group' => $g, 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'pair' => 'mb_risks_open', 'subset_of' => 'mb_risks_open'],
            'mb_risks_critical' => ['label' => 'Risiken kritisch', 'group' => $g, 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'pair' => 'mb_risks_open', 'subset_of' => 'mb_risks_hot'],
            'mb_risks_unrated' => ['label' => 'Risiken unbewertet', 'group' => $g, 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'pair' => 'mb_risks_open', 'subset_of' => 'mb_risks_open'],
            'mb_risk_score_max' => ['label' => 'Höchste Risikopriorität (E × A)', 'group' => $g, 'direction' => 'down', 'unit' => 'score', 'dimension' => 'quality', 'type' => 'modulator', 'aggregation_mode' => 'own', 'roll_up_function' => 'max', 'basis' => 'modulator_factor'],

            'mb_focus_areas' => ['label' => 'Fokusräume', 'group' => $g, 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'mb_milestones_total' => ['label' => 'Strategische Meilensteine', 'group' => $g, 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'mb_milestones_reached' => ['label' => 'Meilensteine erreicht', 'group' => $g, 'direction' => 'up', 'unit' => 'count', 'dimension' => 'throughput', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'pair' => 'mb_milestones_total', 'subset_of' => 'mb_milestones_total'],
            'mb_milestones_overdue' => ['label' => 'Meilensteine überfällig', 'group' => $g, 'direction' => 'down', 'unit' => 'count', 'dimension' => 'energy', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'pair' => 'mb_milestones_total', 'subset_of' => 'mb_milestones_total'],
            'mb_milestones_reached_percent' => ['label' => 'Strategie-Fortschritt (Meilensteine)', 'group' => $g, 'direction' => 'up', 'unit' => 'percentage', 'dimension' => 'throughput', 'type' => 'modulator', 'aggregation_mode' => 'own', 'roll_up_function' => 'avg', 'basis' => 'modulator_factor'],

            'mb_decisions_open' => ['label' => 'Offene Beschlüsse', 'group' => $g, 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'mb_decisions_overdue' => ['label' => 'Beschlüsse überfällig', 'group' => $g, 'direction' => 'down', 'unit' => 'count', 'dimension' => 'energy', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'pair' => 'mb_decisions_open', 'subset_of' => 'mb_decisions_open'],
            'mb_meetings_held_30d' => ['label' => 'Gremiensitzungen (30 Tage)', 'group' => $g, 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_30d'],
            'mb_days_to_next_meeting' => ['label' => 'Tage bis zur nächsten Sitzung', 'group' => $g, 'direction' => 'neutral', 'unit' => 'days', 'dimension' => 'energy', 'type' => 'modulator', 'aggregation_mode' => 'own', 'roll_up_function' => 'min', 'basis' => 'modulator_factor'],
            'mb_meeting_capability_percent' => ['label' => 'Handlungsfähigkeit letzter LA', 'group' => $g, 'direction' => 'up', 'unit' => 'percentage', 'dimension' => 'quality', 'type' => 'modulator', 'aggregation_mode' => 'own', 'roll_up_function' => 'avg', 'basis' => 'modulator_factor'],

            // Gremien an Gremien-Knoten (Alias management_book_committee)
            'mbc_committees' => ['label' => 'Gremien', 'group' => $g, 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'mbc_meetings_held_30d' => ['label' => 'Sitzungen des Gremiums (30 Tage)', 'group' => $g, 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_30d'],
            'mbc_decisions_open' => ['label' => 'Offene Beschlüsse des Gremiums', 'group' => $g, 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'mbc_decisions_overdue' => ['label' => 'Überfällige Beschlüsse des Gremiums', 'group' => $g, 'direction' => 'down', 'unit' => 'count', 'dimension' => 'energy', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'pair' => 'mbc_decisions_open', 'subset_of' => 'mbc_decisions_open'],
            'mbc_days_to_next_meeting' => ['label' => 'Tage bis zur nächsten Gremiumssitzung', 'group' => $g, 'direction' => 'neutral', 'unit' => 'days', 'dimension' => 'energy', 'type' => 'modulator', 'aggregation_mode' => 'own', 'roll_up_function' => 'min', 'basis' => 'modulator_factor'],
            'mbc_meeting_capability_percent' => ['label' => 'Handlungsfähigkeit letzter LA (Gremium)', 'group' => $g, 'direction' => 'up', 'unit' => 'percentage', 'dimension' => 'quality', 'type' => 'modulator', 'aggregation_mode' => 'own', 'roll_up_function' => 'avg', 'basis' => 'modulator_factor'],
        ];
    }

    /** Noch keine Kind-Objekte für den Activity-Feed (Gremien/Sitzungen folgen). */
    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }
}
