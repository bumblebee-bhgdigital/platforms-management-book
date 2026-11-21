<?php

namespace Platform\ManagementBook\Livewire\Committee;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Platform\ManagementBook\Enums\CommitteeMemberRole;
use Platform\ManagementBook\Enums\CommitteeStatus;
use Platform\ManagementBook\Enums\CommitteeType;
use Platform\ManagementBook\Enums\DecisionStatus;
use Platform\ManagementBook\Enums\MeetingStatus;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookDecision;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Services\CommitteeNodeService;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Gremium-Seite: Das Gremium IST ein Organisations-Knoten – Name, Beschreibung, Mitglieder und Vorsitz kommen
 * vom Knoten (Beziehungen is_part_of / manages / is_active_in) und werden dorthin geschrieben. Das Book ergänzt
 * Format des Gremientyps, Rhythmus, Sitzungen (mit Erfolgsmessung) und offene Beschlüsse.
 */
class Show extends Component
{
    public ManagementBookCommittee $committee;

    public bool $showEdit = false;
    public bool $showMember = false;
    public bool $showMeeting = false;

    public array $form = [];
    public array $memberForm = ['entity_id' => null, 'user_id' => null, 'role' => 'member'];
    public array $meetingForm = ['title' => '', 'scheduled_at' => '', 'duration_minutes' => 90, 'location' => ''];

    public bool $placing = false;
    public string $nodeSearch = '';

    public function mount(ManagementBookCommittee $committee): void
    {
        Gate::authorize('view', $committee);
        $this->committee = $committee;
    }

    // ── Gremium ──────────────────────────────────────────────

    public function openEdit(): void
    {
        Gate::authorize('update', $this->committee);
        $c = $this->committee;
        $this->form = [
            'name' => $c->name,
            'type' => $c->type?->value,
            'status' => $c->status?->value,
            'description' => $c->description,
            'cadence' => $c->cadence,
        ];
        $this->resetValidation();
        $this->showEdit = true;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->committee);
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.type' => 'required|in:' . implode(',', CommitteeType::values()),
            'form.status' => 'required|in:' . implode(',', CommitteeStatus::values()),
            'form.description' => 'nullable|string',
            'form.cadence' => 'nullable|string|max:255',
        ]);
        $this->committee->update([
            'type' => $this->form['type'],
            'status' => $this->form['status'],
            'cadence' => $this->form['cadence'] ?: null,
        ]);

        // Name/Beschreibung: am Knoten (Single Source), ohne Knoten im Book
        $service = $this->nodes();
        $nodeId = $service->available() ? $service->nodeId($this->committee) : null;
        if ($nodeId) {
            if (in_array($nodeId, $this->writableEntityIds(), true)) {
                $service->updateNodeText($this->committee, $this->form['name'], $this->form['description'] ?: null, true);
                $this->committee->forceFill(['name' => trim($this->form['name']), 'description' => $this->form['description'] ?: null])->saveQuietly();
            }
        } else {
            $this->committee->update(['name' => trim($this->form['name']), 'description' => $this->form['description'] ?: null]);
        }
        $this->showEdit = false;
    }

    protected function nodes(): CommitteeNodeService
    {
        return app(CommitteeNodeService::class);
    }

    public function setStatus(string $status): void
    {
        Gate::authorize('update', $this->committee);
        if (CommitteeStatus::tryFrom($status)) {
            $this->committee->update(['status' => $status]);
        }
    }

    public function deleteCommittee()
    {
        Gate::authorize('delete', $this->committee);
        $bookId = $this->committee->management_book_id;
        $this->committee->delete();

        return $this->redirect(route('management-book.books.show', $bookId) . '?tab=committees', navigate: true);
    }

    // ── Verortung in der Organisation ────────────────────────

    public function startPlacing(): void
    {
        Gate::authorize('update', $this->committee);
        $this->placing = true;
        $this->nodeSearch = '';
    }

    public function cancelPlacing(): void
    {
        $this->placing = false;
        $this->nodeSearch = '';
    }

    public function placeAt(int $entityId): void
    {
        Gate::authorize('update', $this->committee);
        abort_unless(in_array($entityId, $this->writableEntityIds(), true), 403, 'Kein Schreibrecht auf diesen Knoten.');
        try {
            $this->nodes()->place($this->committee, $entityId);
        } catch (\DomainException $e) {
            $this->addError('placement', $e->getMessage());

            return;
        }
        $this->cancelPlacing();
    }

    public function unplace(): void
    {
        Gate::authorize('update', $this->committee);
        app(CommitteeNodeService::class)->unplace($this->committee);
    }

    /** @return int[] */
    protected function writableEntityIds(): array
    {
        if (! class_exists(\Platform\Organization\Authz\DimensionLinkAuthorizer::class) || ! Auth::user()) {
            return [];
        }

        return (new \Platform\Organization\Authz\DimensionLinkAuthorizer())->writableEntityIds(Auth::user());
    }

    /** @return array<int, array{id:int,name:string,type:?string}> */
    protected function nodeCandidates(): array
    {
        $writable = $this->placing ? $this->writableEntityIds() : [];
        if ($writable === []) {
            return [];
        }
        $search = trim($this->nodeSearch);
        $service = $this->nodes();
        $typeIds = \Platform\Organization\Models\OrganizationEntityType::whereIn('code', CommitteeNodeService::COMMITTEE_TYPE_CODES)->pluck('id');
        $taken = ManagementBookCommittee::where('team_id', $this->committee->team_id)->whereKeyNot($this->committee->id)
            ->whereNotNull('organization_entity_id')->pluck('organization_entity_id')->all();

        return OrganizationEntity::query()->with('type:id,name')->whereIn('id', $writable)->where('is_active', true)
            ->when($typeIds->isNotEmpty(), fn ($q) => $q->whereIn('entity_type_id', $typeIds))
            ->whereNotIn('id', $taken)
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->limit($search !== '' ? 20 : 10)->get(['id', 'name', 'entity_type_id'])
            ->map(fn ($e) => ['id' => (int) $e->id, 'name' => $e->name, 'type' => $e->type?->name])->all();
    }

    // ── Mitglieder ───────────────────────────────────────────

    public function openMember(): void
    {
        Gate::authorize('update', $this->committee);
        $this->memberForm = ['entity_id' => null, 'user_id' => null, 'role' => 'member'];
        $this->resetValidation();
        $this->showMember = true;
    }

    public function addMember(): void
    {
        Gate::authorize('update', $this->committee);
        $service = $this->nodes();
        $nodeId = $service->available() ? $service->nodeId($this->committee) : null;

        if ($nodeId) {
            // Mitglieder sind Beziehungen am Gremien-Knoten
            abort_unless(in_array($nodeId, $this->writableEntityIds(), true), 403, 'Kein Schreibrecht auf den Gremien-Knoten.');
            $this->validate([
                'memberForm.entity_id' => 'required|integer',
                'memberForm.role' => 'required|in:chair,member,agent',
            ]);
            $service->setMember($this->committee, (int) $this->memberForm['entity_id'], $this->memberForm['role']);
            $this->showMember = false;

            return;
        }

        $this->validate([
            'memberForm.user_id' => 'required|integer|exists:users,id',
            'memberForm.role' => 'required|in:' . implode(',', CommitteeMemberRole::values()),
        ]);
        $user = \Platform\Core\Models\User::find((int) $this->memberForm['user_id']);
        $col = ($user?->type ?? null) === 'ai_user' ? 'agent_id' : 'user_id';
        $exists = $this->committee->members()->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('agent_id', $user->id);
        })->first();
        if ($exists) {
            $exists->update(['role' => $this->memberForm['role']]);
        } else {
            $this->committee->members()->create([$col => $user->id, 'role' => $this->memberForm['role']]);
        }
        $this->showMember = false;
    }

    /** $key: e<entity_id> (Beziehung am Knoten) oder m<member_id> (Book-Liste, Übergang). */
    public function removeMember(string $key): void
    {
        Gate::authorize('update', $this->committee);
        $id = (int) substr($key, 1);
        if (str_starts_with($key, 'e')) {
            $nodeId = $this->nodes()->nodeId($this->committee);
            abort_unless($nodeId && in_array($nodeId, $this->writableEntityIds(), true), 403, 'Kein Schreibrecht auf den Gremien-Knoten.');
            $this->nodes()->removeMember($this->committee, $id);

            return;
        }
        $this->committee->members()->whereKey($id)->delete();
    }

    // ── Sitzungen ────────────────────────────────────────────

    public function openMeeting(): void
    {
        Gate::authorize('update', $this->committee);
        $this->meetingForm = [
            'title' => $this->committee->name . ' ' . now()->translatedFormat('F Y'),
            'scheduled_at' => now()->addWeek()->setTime(10, 0)->format('Y-m-d\TH:i'),
            'duration_minutes' => 90,
            'location' => '',
        ];
        $this->resetValidation();
        $this->showMeeting = true;
    }

    public function createMeeting()
    {
        Gate::authorize('update', $this->committee);
        $this->validate([
            'meetingForm.title' => 'required|string|max:255',
            'meetingForm.scheduled_at' => 'required|date',
            'meetingForm.duration_minutes' => 'nullable|integer|min:1|max:1440',
            'meetingForm.location' => 'nullable|string|max:255',
        ]);
        $meeting = ManagementBookMeeting::create([
            'team_id' => $this->committee->team_id,
            'committee_id' => $this->committee->id,
            'title' => trim($this->meetingForm['title']),
            'scheduled_at' => $this->meetingForm['scheduled_at'],
            'duration_minutes' => $this->meetingForm['duration_minutes'] ?: null,
            'location' => $this->meetingForm['location'] ?: null,
            'created_by' => Auth::id(),
        ]);
        $this->showMeeting = false;

        return $this->redirect(route('management-book.meetings.show', $meeting), navigate: true);
    }

    // ── Beschlüsse ───────────────────────────────────────────

    public function setDecisionStatus(int $decisionId, string $status): void
    {
        $decision = ManagementBookDecision::findOrFail($decisionId);
        Gate::authorize('update', $decision);
        abort_unless($decision->meeting?->committee_id === $this->committee->id, 404);
        if (DecisionStatus::tryFrom($status)) {
            $decision->update(['status' => $status]);
        }
    }

    public function render()
    {
        $committee = $this->committee->fresh(['book:id,title,team_id', 'responsibleAgent:id,name', 'members.user:id,name', 'members.agent:id,name']);

        $meetings = $committee->meetings()
            ->withCount(['agendaItems', 'decisions'])
            ->with('agendaItems:id,meeting_id,kind,outcome_checks,goal,observation,obstacle,local_solution,system_support')
            ->orderByDesc('scheduled_at')
            ->get();

        $isUpcoming = fn (ManagementBookMeeting $m) => $m->scheduled_at && $m->scheduled_at->gte(now()->startOfDay())
            && ! in_array($m->status, [MeetingStatus::HELD, MeetingStatus::CANCELLED], true);
        $upcoming = $meetings->filter($isUpcoming)->sortBy('scheduled_at')->values();
        $past = $meetings->reject($isUpcoming)->values();

        // Erfolg je vergangener Sitzung nach dem Format (LA: Handlungsfähigkeit, Strategie: Richtungsklarheit, …)
        $format = $committee->format();
        $capability = [];
        foreach ($past as $m) {
            $a = $format->assess($m->agendaItems);
            if ($a['guided'] > 0) {
                $capability[$m->id] = $a;
            }
        }

        $nodeService = $this->nodes();
        $node = $nodeService->node($committee);
        $nodes = $node ? collect([$node]) : collect();
        $members = $nodeService->members($committee);
        $nodeWritable = $node && in_array((int) $node->id, $this->writableEntityIds(), true);

        $openDecisions = ManagementBookDecision::query()
            ->whereIn('meeting_id', $meetings->pluck('id'))
            ->where('status', DecisionStatus::OPEN->value)
            ->with(['meeting:id,title,scheduled_at', 'ownerUser:id,name', 'ownerAgent:id,name'])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get();

        $memberIds = DB::table('team_user')->where('team_id', $committee->team_id)->pluck('user_id');

        return view('management-book::livewire.committee.show', [
            'committee' => $committee,
            'book' => $committee->book,
            'upcoming' => $upcoming,
            'past' => $past,
            'capability' => $capability,
            'format' => $format,
            'nodes' => $nodes,
            'node' => $node,
            'nodeUrl' => $nodeService->nodeUrl($node?->id),
            'nodeWritable' => $nodeWritable,
            'members' => $members,
            'chair' => $members->firstWhere('role', 'chair') ?? $nodeService->chair($committee),
            'personOptions' => $node && $this->showMember ? $nodeService->personOptions((int) $committee->team_id) : [],
            'nodeRoleOptions' => [['value' => 'chair', 'label' => 'Vorsitz'], ['value' => 'member', 'label' => 'Mitglied'], ['value' => 'agent', 'label' => 'Agent']],
            'nodesAvailable' => $nodeService->available(),
            'nodeCandidates' => $this->nodeCandidates(),
            'openDecisions' => $openDecisions,
            'canEdit' => Gate::allows('update', $committee),
            'typeOptions' => array_map(fn ($t) => ['value' => $t->value, 'label' => $t->label()], CommitteeType::cases()),
            'statusOptions' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], CommitteeStatus::cases()),
            'roleOptions' => array_map(fn ($r) => ['value' => $r->value, 'label' => $r->label()], CommitteeMemberRole::cases()),
            'userOptions' => \Platform\Core\Models\User::whereIn('id', $memberIds)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
        ])->layout('platform::layouts.app');
    }

    public static function meetingBadge(?MeetingStatus $s): string
    {
        return match ($s) {
            MeetingStatus::PREPARED => 'info',
            MeetingStatus::HELD => 'success',
            MeetingStatus::CANCELLED => 'neutral',
            default => 'warning',
        };
    }
}
