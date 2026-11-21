<?php

namespace Platform\ManagementBook\People;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Platform\ManagementBook\Enums\DecisionStatus;
use Platform\ManagementBook\Enums\MilestoneStatus;
use Platform\ManagementBook\Models\ManagementBookAgendaItem;
use Platform\ManagementBook\Models\ManagementBookDecision;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\People\Support\ImpactSourceBase;

/**
 * Wirkungs-Quelle Management Book für people: erreichte Meilensteine der Transformation Map
 * (mit dem, was auf sie einzahlte) und Aufgegriffenes — ein Objekt, das auf die Agenda eines
 * Gremiums kam und dort zu einem Beschluss führte. Lose gekoppelt — nur registriert, wenn people geladen ist.
 */
class ManagementBookImpactSource extends ImpactSourceBase
{
    /** Kein Morph-Alias registriert — Bezeichner nach Konvention "alias:id". */
    protected const MILESTONE_REF = 'management_book_milestone';
    protected const DECISION_REF = 'management_book_decision';

    public function key(): string
    {
        return 'management-book';
    }

    public function milestones(int $teamId, CarbonInterface $from, CarbonInterface $to): array
    {
        $milestones = ManagementBookMilestone::query()
            // kein Team-Filter: Bücher liegen oft in einem anderen Team als die Mitarbeiter; gezählt wird
            // ohnehin nur, was exakt auf Arbeit eines Mitarbeiters verweist (Ref-genau)
            ->where('status', MilestoneStatus::REACHED->value)
            ->whereNotNull('reached_at')
            ->whereDate('reached_at', '>=', $from->toDateString())
            ->whereDate('reached_at', '<=', $to->toDateString())
            ->with('contributions')
            ->get();

        return $milestones->map(fn (ManagementBookMilestone $m) => [
            'ref' => self::MILESTONE_REF.':'.$m->id,
            'title' => $m->title,
            'reached_at' => Carbon::parse($m->reached_at),
            // linkable_type ist bereits Morph-Alias (okr_objective, okr_key_result, …); weight 1–100 → 0..1
            'contributors' => $m->contributions->map(fn ($c) => [
                'ref' => $c->linkable_type.':'.$c->linkable_id,
                'weight' => ($c->weight ?? 100) / 100,
            ])->values()->all(),
        ])->values()->all();
    }

    public function adoptions(int $teamId, CarbonInterface $from, CarbonInterface $to): array
    {
        $decisions = ManagementBookDecision::query()
            ->whereBetween('created_at', [$from, $to])
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', DecisionStatus::DISCARDED->value))
            ->whereNotNull('agenda_item_id')
            ->get(['id', 'agenda_item_id', 'text', 'created_at']);

        if ($decisions->isEmpty()) {
            return [];
        }

        // Herkunft: das mit dem Agenda-Punkt verknüpfte Objekt (Alias normalisiert beim Speichern)
        $items = ManagementBookAgendaItem::withTrashed()
            ->whereIn('id', $decisions->pluck('agenda_item_id')->unique()->all())
            ->whereNotNull('linked_object_type')
            ->whereNotNull('linked_object_id')
            ->get(['id', 'linked_object_type', 'linked_object_id'])
            ->keyBy('id');

        $out = [];
        foreach ($decisions as $d) {
            $item = $items->get($d->agenda_item_id);
            if (! $item) {
                continue;
            }
            $out[] = [
                'ref' => self::DECISION_REF.':'.$d->id,
                'title' => Str::limit((string) $d->text, 80),
                'origin_ref' => $item->linked_object_type.':'.$item->linked_object_id,
                'at' => $d->created_at,
            ];
        }

        return $out;
    }
}
