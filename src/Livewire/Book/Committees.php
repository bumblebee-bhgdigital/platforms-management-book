<?php

namespace Platform\ManagementBook\Livewire\Book;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\ManagementBook\Enums\CommitteeStatus;
use Platform\ManagementBook\Enums\CommitteeType;
use Platform\ManagementBook\Enums\DecisionStatus;
use Platform\ManagementBook\Enums\MeetingStatus;
use Platform\ManagementBook\Livewire\Concerns\LoadsBook;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookDecision;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Services\CommitteeNodeService;

/**
 * Tab „Gremien“ eines Management Books: Gremien als Liste (Typ, Vorsitz, Mitglieder, Rhythmus,
 * nächste Sitzung, offene Beschlüsse) und Anlegen. Ein Gremium IST ein Organisations-Knoten: Anlegen heißt,
 * einen vorhandenen Gremien-Knoten zu aktivieren oder einen neuen (unter „Governance“ der Einheit) anzulegen.
 */
class Committees extends Component
{
    use LoadsBook;

    public bool $showCreate = false;

    public array $form = [
        'name' => '',
        'type' => 'steering_committee',
        'description' => '',
        'cadence' => '',
        'chair_user_id' => null,
        'entity_id' => null,
    ];

    /** Vorschläge als Startpunkt (nur Vorbelegung des Formulars, keine Automatik). */
    public const PRESETS = [
        'strategy_meeting' => [
            'name' => 'Strategiemeeting GF',
            'type' => 'strategy_meeting',
            'cadence' => 'halbjährlich',
            'description' => 'Die Geschäftsführung klärt die Grundsatzfragen: Entwicklungen, Chancen, Risiken, Geschäftsfelder, wirtschaftliche Ziele und benötigte Fähigkeiten.',
        ],
        'steering_committee' => [
            'name' => 'Lenkungsausschuss',
            'type' => 'steering_committee',
            'cadence' => 'monatlich',
            'description' => 'Regelschleife für das, was zwischen den Einheiten klemmt: Zielkonflikte, geteilte Ressourcen, Prioritäten über Bereichsgrenzen. Lokal vor übergreifend.',
        ],
    ];

    public function openCreate(?string $preset = null, ?int $entityId = null): void
    {
        $this->authorizeEdit();
        $this->reset('form');
        if ($preset && isset(self::PRESETS[$preset])) {
            $this->form = array_merge($this->form, self::PRESETS[$preset]);
        }
        if ($entityId && class_exists(\Platform\Organization\Models\OrganizationEntity::class)) {
            $node = \Platform\Organization\Models\OrganizationEntity::find($entityId);
            if ($node) {
                $name = mb_strtolower($node->name);
                $this->form = array_merge($this->form, [
                    'entity_id' => $node->id,
                    'name' => $node->name,
                    'description' => (string) $node->description,
                    'type' => str_contains($name, 'lenkung') ? 'steering_committee' : (str_contains($name, 'strateg') ? 'strategy_meeting' : (str_contains($name, 'board') ? 'board' : 'other')),
                ]);
            }
        }
        $this->resetValidation();
        $this->showCreate = true;
    }

    public function create()
    {
        $book = $this->authorizeEdit();
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.type' => 'required|in:' . implode(',', CommitteeType::values()),
            'form.description' => 'nullable|string',
            'form.cadence' => 'nullable|string|max:255',
            'form.chair_user_id' => 'nullable|integer|exists:users,id',
            'form.entity_id' => 'nullable|integer',
        ]);

        $service = app(CommitteeNodeService::class);
        $writable = $this->writableEntityIds();
        $entityId = (int) ($this->form['entity_id'] ?? 0);

        $nodeMode = $service->nodeMode();
        if ($nodeMode) {
            if ($entityId) {
                if (! in_array($entityId, $writable, true)) {
                    $this->addError('form.entity_id', 'Kein Schreibrecht auf diesen Gremien-Knoten.');

                    return null;
                }
                if ($service->committeeAt($entityId, (int) $book->team_id)) {
                    $this->addError('form.entity_id', 'Dieser Knoten ist bereits ein Gremium.');

                    return null;
                }
            } else {
                $unitId = $service->unitNodeIds($book)[0] ?? null;
                if (! $unitId) {
                    $this->addError('form.name', 'Das Book hängt an keinem Organisations-Knoten – verorte es zuerst, dann entsteht das Gremium unter „Governance“ der Einheit.');

                    return null;
                }
                if (! in_array($unitId, $writable, true)) {
                    $this->addError('form.name', 'Kein Schreibrecht auf die Einheit in der Organisation – der Gremien-Knoten kann nicht angelegt werden.');

                    return null;
                }
            }
        }

        $committee = DB::transaction(function () use ($book, $service, $entityId, $nodeMode) {
            $node = null;
            if ($nodeMode) {
                $node = $entityId
                    ? \Platform\Organization\Models\OrganizationEntity::findOrFail($entityId)
                    : $service->createNode($book, trim($this->form['name']), null, $this->form['description'] ?: null);
            }
            $committee = ManagementBookCommittee::create([
                'team_id' => $book->team_id,
                'management_book_id' => $book->id,
                'name' => $node?->name ?? trim($this->form['name']),
                'type' => $this->form['type'],
                'description' => $node ? $node->description : ($this->form['description'] ?: null),
                'cadence' => $this->form['cadence'] ?: null,
                'chair_user_id' => $node ? null : ($this->form['chair_user_id'] ?: null),
                'created_by' => Auth::id(),
            ]);
            if ($node) {
                $service->place($committee, $node->id);
                $personId = $this->form['chair_user_id'] ? $service->personEntityIdForUser((int) $this->form['chair_user_id']) : null;
                if ($personId) {
                    $service->setMember($committee, $personId, 'chair');
                }
            } elseif ($committee->chair_user_id) {
                $committee->members()->create(['user_id' => $committee->chair_user_id, 'role' => 'chair']);
            }

            return $committee;
        });

        $this->showCreate = false;

        return $this->redirect(route('management-book.committees.show', $committee), navigate: true);
    }

    /** @return int[] */
    protected function writableEntityIds(): array
    {
        if (! class_exists(\Platform\Organization\Authz\DimensionLinkAuthorizer::class) || ! Auth::user()) {
            return [];
        }

        return (new \Platform\Organization\Authz\DimensionLinkAuthorizer())->writableEntityIds(Auth::user());
    }

    public function render()
    {
        $book = $this->book();
        $committees = ManagementBookCommittee::query()
            ->where('management_book_id', $book->id)
            ->with(['members.user:id,name', 'members.agent:id,name'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'dormant' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        $service = app(CommitteeNodeService::class);
        $people = $committees->mapWithKeys(fn ($c) => [$c->id => $service->members($c)]);
        $chairs = $committees->mapWithKeys(fn ($c) => [$c->id => $people[$c->id]->firstWhere('role', 'chair') ?? $service->chair($c)]);

        $ids = $committees->pluck('id');
        $nextMeetings = ManagementBookMeeting::query()
            ->whereIn('committee_id', $ids)
            ->where('scheduled_at', '>=', now())
            ->whereNotIn('status', [MeetingStatus::CANCELLED->value, MeetingStatus::HELD->value])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy('committee_id')
            ->map->first();

        $openDecisions = ManagementBookDecision::query()
            ->join('management_book_meetings as m', 'm.id', '=', 'management_book_decisions.meeting_id')
            ->whereNull('m.deleted_at')
            ->whereIn('m.committee_id', $ids)
            ->where('management_book_decisions.status', DecisionStatus::OPEN->value)
            ->selectRaw('m.committee_id, count(*) as n, sum(case when management_book_decisions.due_date < ? then 1 else 0 end) as overdue', [now()->toDateString()])
            ->groupBy('m.committee_id')
            ->get()
            ->keyBy('committee_id');

        return view('management-book::livewire.book.committees', [
            'book' => $book,
            'committees' => $committees,
            'people' => $people,
            'chairs' => $chairs,
            'suggestions' => $service->suggestions($book),
            'nodesAvailable' => $service->nodeMode(),
            'nextMeetings' => $nextMeetings,
            'openDecisions' => $openDecisions,
            'canEdit' => $this->canEdit(),
            'typeOptions' => array_map(fn (CommitteeType $t) => ['value' => $t->value, 'label' => $t->label()], CommitteeType::cases()),
            'userOptions' => $this->teamUserOptions($book->team_id),
        ]);
    }

    /** @return array<int, array{value:int,label:string}> */
    protected function teamUserOptions(int $teamId): array
    {
        $ids = DB::table('team_user')->where('team_id', $teamId)->pluck('user_id');

        return \Platform\Core\Models\User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all();
    }

    public static function typeBadge(?CommitteeType $type): string
    {
        return match ($type) {
            CommitteeType::STEERING_COMMITTEE => 'info',
            CommitteeType::STRATEGY_MEETING => 'accent',
            default => 'neutral',
        };
    }

    public static function statusBadge(?CommitteeStatus $status): string
    {
        return match ($status) {
            CommitteeStatus::ACTIVE => 'success',
            CommitteeStatus::DORMANT => 'warning',
            default => 'neutral',
        };
    }
}
