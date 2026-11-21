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

/**
 * Tab „Gremien“ eines Management Books: Gremien als Liste (Typ, Vorsitz, Mitglieder, Rhythmus,
 * nächste Sitzung, offene Beschlüsse) und Anlegen. Reine Datenhaltung, keine Ablauflogik.
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

    public function openCreate(?string $preset = null): void
    {
        $this->authorizeEdit();
        $this->reset('form');
        if ($preset && isset(self::PRESETS[$preset])) {
            $this->form = array_merge($this->form, self::PRESETS[$preset]);
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
        ]);

        $committee = ManagementBookCommittee::create([
            'team_id' => $book->team_id,
            'management_book_id' => $book->id,
            'name' => trim($this->form['name']),
            'type' => $this->form['type'],
            'description' => $this->form['description'] ?: null,
            'cadence' => $this->form['cadence'] ?: null,
            'chair_user_id' => $this->form['chair_user_id'] ?: null,
            'created_by' => Auth::id(),
        ]);
        if ($committee->chair_user_id) {
            $committee->members()->create(['user_id' => $committee->chair_user_id, 'role' => 'chair']);
        }

        $this->showCreate = false;

        return $this->redirect(route('management-book.committees.show', $committee), navigate: true);
    }

    public function render()
    {
        $book = $this->book();
        $committees = ManagementBookCommittee::query()
            ->where('management_book_id', $book->id)
            ->with(['chair:id,name'])
            ->withCount('members')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'dormant' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

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
