<?php

namespace Platform\ManagementBook\Livewire\Meeting;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Platform\ManagementBook\Enums\AgendaOutcomeCheck;
use Platform\ManagementBook\Enums\DecisionStatus;
use Platform\ManagementBook\Enums\MeetingStatus;
use Platform\ManagementBook\Models\ManagementBookAgendaItem;
use Platform\ManagementBook\Models\ManagementBookDecision;
use Platform\ManagementBook\Models\ManagementBookDocument;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Models\ManagementBookMeetingAttendee;
use Platform\ManagementBook\Models\ManagementBookMinutes;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Sitzungsseite mit Lenkungsausschuss-Leitfaden (BROICH.MANAGEMENTMODELL, „Die vier Fragen“):
 * Agenda mit den vier Fragen und Handlungsfähigkeits-Kriterien, Beschlüsse, Teilnehmer, Protokoll, Unterlagen.
 * Alles frei schreibbar, keine Automatik.
 */
class Show extends Component
{
    public ManagementBookMeeting $meeting;

    public bool $showMeetingEdit = false;
    public bool $showItem = false;
    public bool $showDecision = false;
    public bool $showAttendee = false;
    public bool $showMinutes = false;
    public bool $showDocument = false;

    public ?int $editingItemId = null;
    public ?int $editingDecisionId = null;
    public ?int $editingMinutesId = null;

    public array $meetingForm = [];
    public array $itemForm = [];
    public array $decisionForm = [];
    public array $attendeeForm = ['user_id' => null, 'role' => 'member'];
    public array $minutesForm = ['content' => ''];
    public array $documentForm = ['title' => '', 'type' => '', 'agenda_item_id' => null];

    public function mount(ManagementBookMeeting $meeting): void
    {
        Gate::authorize('view', $meeting);
        $this->meeting = $meeting;
    }

    protected function authorizeEdit(): void
    {
        Gate::authorize('update', $this->meeting);
    }

    /** Kind muss zu dieser Sitzung gehören. */
    protected function child(string $class, int $id)
    {
        $model = $class::where('meeting_id', $this->meeting->id)->findOrFail($id);
        Gate::authorize('update', $model);

        return $model;
    }

    // ── Sitzung ──────────────────────────────────────────────

    public function setStatus(string $status): void
    {
        $this->authorizeEdit();
        if (MeetingStatus::tryFrom($status)) {
            $this->meeting->update(['status' => $status]);
        }
    }

    public function openMeetingEdit(): void
    {
        $this->authorizeEdit();
        $m = $this->meeting;
        $this->meetingForm = [
            'title' => $m->title,
            'scheduled_at' => $m->scheduled_at?->format('Y-m-d\TH:i'),
            'duration_minutes' => $m->duration_minutes,
            'location' => $m->location,
        ];
        $this->resetValidation();
        $this->showMeetingEdit = true;
    }

    public function saveMeeting(): void
    {
        $this->authorizeEdit();
        $this->validate([
            'meetingForm.title' => 'required|string|max:255',
            'meetingForm.scheduled_at' => 'nullable|date',
            'meetingForm.duration_minutes' => 'nullable|integer|min:1|max:1440',
            'meetingForm.location' => 'nullable|string|max:255',
        ]);
        $this->meeting->update([
            'title' => trim($this->meetingForm['title']),
            'scheduled_at' => $this->meetingForm['scheduled_at'] ?: null,
            'duration_minutes' => $this->meetingForm['duration_minutes'] ?: null,
            'location' => $this->meetingForm['location'] ?: null,
        ]);
        $this->showMeetingEdit = false;
    }

    public function deleteMeeting()
    {
        Gate::authorize('delete', $this->meeting);
        $committeeId = $this->meeting->committee_id;
        $this->meeting->delete();

        return $this->redirect(route('management-book.committees.show', $committeeId), navigate: true);
    }

    // ── Agenda mit Leitfaden ─────────────────────────────────

    public function openItem(?int $itemId = null): void
    {
        $this->authorizeEdit();
        $this->editingItemId = $itemId;
        $item = $itemId ? $this->child(ManagementBookAgendaItem::class, $itemId) : null;
        $this->itemForm = [
            'title' => $item?->title ?? '',
            'description' => $item?->description ?? '',
            'owner_user_id' => $item?->owner_user_id,
            'duration_minutes' => $item?->duration_minutes,
            'organization_entity_id' => $item?->organization_entity_id,
            'goal' => $item?->goal ?? '',
            'observation' => $item?->observation ?? '',
            'obstacle' => $item?->obstacle ?? '',
            'local_solution' => $item?->local_solution ?? '',
            'system_support' => $item?->system_support ?? '',
        ];
        $this->resetValidation();
        $this->showItem = true;
    }

    public function saveItem(): void
    {
        $this->authorizeEdit();
        $this->validate([
            'itemForm.title' => 'required|string|max:255',
            'itemForm.description' => 'nullable|string',
            'itemForm.owner_user_id' => 'nullable|integer|exists:users,id',
            'itemForm.duration_minutes' => 'nullable|integer|min:1|max:1440',
            'itemForm.organization_entity_id' => 'nullable|integer',
            'itemForm.goal' => 'nullable|string',
            'itemForm.observation' => 'nullable|string',
            'itemForm.obstacle' => 'nullable|string',
            'itemForm.local_solution' => 'nullable|string',
            'itemForm.system_support' => 'nullable|string',
        ]);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $this->itemForm);
        $data['title'] = trim((string) $data['title']);

        if ($this->editingItemId) {
            $this->child(ManagementBookAgendaItem::class, $this->editingItemId)->update($data);
        } else {
            $data['position'] = (int) $this->meeting->agendaItems()->max('position') + 1;
            $data['created_by'] = Auth::id();
            $this->meeting->agendaItems()->create($data);
        }
        $this->showItem = false;
    }

    public function moveItem(int $itemId, int $direction): void
    {
        $this->authorizeEdit();
        $items = $this->meeting->agendaItems()->get()->values();
        $index = $items->search(fn ($i) => $i->id === $itemId);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
        if ($index === false || $target < 0 || $target >= $items->count()) {
            return;
        }
        $ordered = $items->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $pos => $item) {
                if ($item->position !== $pos + 1) {
                    $item->update(['position' => $pos + 1]);
                }
            }
        });
    }

    public function deleteItem(int $itemId): void
    {
        $this->child(ManagementBookAgendaItem::class, $itemId)->delete();
    }

    public function toggleCheck(int $itemId, string $check): void
    {
        if (! AgendaOutcomeCheck::tryFrom($check)) {
            return;
        }
        $item = $this->child(ManagementBookAgendaItem::class, $itemId);
        $checks = $item->outcome_checks ?? [];
        $checks = in_array($check, $checks, true)
            ? array_values(array_diff($checks, [$check]))
            : array_values(array_unique([...$checks, $check]));
        $item->update(['outcome_checks' => $checks]);
    }

    // ── Beschlüsse ───────────────────────────────────────────

    public function openDecision(?int $decisionId = null, ?int $agendaItemId = null): void
    {
        $this->authorizeEdit();
        $this->editingDecisionId = $decisionId;
        $d = $decisionId ? $this->child(ManagementBookDecision::class, $decisionId) : null;
        $this->decisionForm = [
            'text' => $d?->text ?? '',
            'status' => $d?->status?->value ?? DecisionStatus::OPEN->value,
            'owner_user_id' => $d?->owner_user_id,
            'due_date' => $d?->due_date?->toDateString(),
            'agenda_item_id' => $d?->agenda_item_id ?? $agendaItemId,
        ];
        $this->resetValidation();
        $this->showDecision = true;
    }

    public function saveDecision(): void
    {
        $this->authorizeEdit();
        $this->validate([
            'decisionForm.text' => 'required|string',
            'decisionForm.status' => 'required|in:' . implode(',', DecisionStatus::values()),
            'decisionForm.owner_user_id' => 'nullable|integer|exists:users,id',
            'decisionForm.due_date' => 'nullable|date',
            'decisionForm.agenda_item_id' => 'nullable|integer',
        ]);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $this->decisionForm);
        $data['text'] = trim((string) $data['text']);
        if ($data['agenda_item_id'] && ! $this->meeting->agendaItems()->whereKey($data['agenda_item_id'])->exists()) {
            $data['agenda_item_id'] = null;
        }
        if ($data['owner_user_id']) {
            $data['owner_agent_id'] = null;
        }

        if ($this->editingDecisionId) {
            $this->child(ManagementBookDecision::class, $this->editingDecisionId)->update($data);
        } else {
            $data['created_by'] = Auth::id();
            $this->meeting->decisions()->create($data);
        }
        $this->showDecision = false;
    }

    public function setDecisionStatus(int $decisionId, string $status): void
    {
        if (DecisionStatus::tryFrom($status)) {
            $this->child(ManagementBookDecision::class, $decisionId)->update(['status' => $status]);
        }
    }

    public function deleteDecision(int $decisionId): void
    {
        $this->child(ManagementBookDecision::class, $decisionId)->delete();
    }

    // ── Teilnehmer ───────────────────────────────────────────

    public function adoptMembers(): void
    {
        $this->authorizeEdit();
        $existing = $this->meeting->attendees()->get();
        foreach ($this->meeting->committee->members as $member) {
            $already = $existing->first(fn ($a) => ($member->user_id && $a->user_id === $member->user_id)
                || ($member->agent_id && $a->agent_id === $member->agent_id));
            if (! $already) {
                $this->meeting->attendees()->create([
                    'user_id' => $member->user_id,
                    'agent_id' => $member->agent_id,
                    'role' => $member->role?->value,
                    'attended' => false,
                ]);
            }
        }
    }

    public function openAttendee(): void
    {
        $this->authorizeEdit();
        $this->attendeeForm = ['user_id' => null, 'role' => 'member'];
        $this->resetValidation();
        $this->showAttendee = true;
    }

    public function addAttendee(): void
    {
        $this->authorizeEdit();
        $this->validate([
            'attendeeForm.user_id' => 'required|integer|exists:users,id',
            'attendeeForm.role' => 'nullable|string|max:50',
        ]);
        $user = \Platform\Core\Models\User::find((int) $this->attendeeForm['user_id']);
        $col = ($user->type ?? null) === 'ai_user' ? 'agent_id' : 'user_id';
        $exists = $this->meeting->attendees()->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('agent_id', $user->id))->exists();
        if (! $exists) {
            $this->meeting->attendees()->create([$col => $user->id, 'role' => $this->attendeeForm['role'] ?: null, 'attended' => false]);
        }
        $this->showAttendee = false;
    }

    public function toggleAttended(int $attendeeId): void
    {
        $a = $this->child(ManagementBookMeetingAttendee::class, $attendeeId);
        $a->update(['attended' => ! $a->attended]);
    }

    public function removeAttendee(int $attendeeId): void
    {
        $this->child(ManagementBookMeetingAttendee::class, $attendeeId)->delete();
    }

    // ── Protokoll ────────────────────────────────────────────

    public function openMinutes(?int $minutesId = null): void
    {
        $this->authorizeEdit();
        $this->editingMinutesId = $minutesId;
        $min = $minutesId ? $this->child(ManagementBookMinutes::class, $minutesId) : null;
        $this->minutesForm = ['content' => $min?->content ?? $this->minutesTemplate()];
        $this->showMinutes = true;
    }

    /** Vorschlag aus der Agenda (nur Vorbelegung im Editor, nichts wird automatisch gespeichert). */
    protected function minutesTemplate(): string
    {
        $lines = ['# Protokoll ' . $this->meeting->title, ''];
        foreach ($this->meeting->agendaItems as $i => $item) {
            $lines[] = '## ' . ($i + 1) . '. ' . $item->title;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public function saveMinutes(): void
    {
        $this->authorizeEdit();
        $this->validate(['minutesForm.content' => 'nullable|string']);
        if ($this->editingMinutesId) {
            $this->child(ManagementBookMinutes::class, $this->editingMinutesId)->update(['content' => $this->minutesForm['content']]);
        } else {
            $this->meeting->minutes()->create(['content' => $this->minutesForm['content'], 'created_by' => Auth::id()]);
        }
        $this->showMinutes = false;
    }

    public function toggleMinutesApproval(int $minutesId): void
    {
        $min = $this->child(ManagementBookMinutes::class, $minutesId);
        $min->update($min->approved_at
            ? ['approved_at' => null, 'approved_by' => null]
            : ['approved_at' => now(), 'approved_by' => Auth::id()]);
    }

    public function deleteMinutes(int $minutesId): void
    {
        $this->child(ManagementBookMinutes::class, $minutesId)->delete();
    }

    // ── Unterlagen ───────────────────────────────────────────

    public function openDocument(): void
    {
        $this->authorizeEdit();
        $this->documentForm = ['title' => '', 'type' => '', 'agenda_item_id' => null];
        $this->resetValidation();
        $this->showDocument = true;
    }

    public function saveDocument(): void
    {
        $this->authorizeEdit();
        $this->validate([
            'documentForm.title' => 'required|string|max:255',
            'documentForm.type' => 'nullable|string|max:100',
            'documentForm.agenda_item_id' => 'nullable|integer',
        ]);
        $agendaId = $this->documentForm['agenda_item_id'] ?: null;
        if ($agendaId && ! $this->meeting->agendaItems()->whereKey($agendaId)->exists()) {
            $agendaId = null;
        }
        $this->meeting->documents()->create([
            'title' => trim($this->documentForm['title']),
            'type' => $this->documentForm['type'] ?: null,
            'agenda_item_id' => $agendaId,
            'created_by' => Auth::id(),
        ]);
        $this->showDocument = false;
    }

    public function deleteDocument(int $documentId): void
    {
        $this->child(ManagementBookDocument::class, $documentId)->delete();
    }

    // ── Render ───────────────────────────────────────────────

    public function render()
    {
        $meeting = $this->meeting->fresh([
            'committee.book:id,title,team_id',
            'committee.members',
            'agendaItems.ownerUser:id,name',
            'decisions.ownerUser:id,name',
            'decisions.ownerAgent:id,name',
            'attendees.user:id,name',
            'attendees.agent:id,name',
            'minutes.approver:id,name',
            'documents',
        ]);
        $this->meeting = $meeting;

        $items = $meeting->agendaItems;
        $guided = $items->filter(fn ($i) => $this->isGuided($i));
        $done = $guided->sum(fn ($i) => count($i->outcome_checks ?? []));
        $max = $guided->count() * count(AgendaOutcomeCheck::cases());

        $entityIds = $items->pluck('organization_entity_id')->filter()->unique();
        $teamId = $meeting->committee?->book?->team_id ?? $meeting->team_id;

        $documentFiles = [];
        foreach ($meeting->documents as $doc) {
            try {
                $documentFiles[$doc->id] = $doc->fileReferences()->with('contextFile')->get()
                    ->map(fn ($ref) => $ref->contextFile?->original_name)->filter()->values()->all();
            } catch (\Throwable) {
                $documentFiles[$doc->id] = [];
            }
        }

        return view('management-book::livewire.meeting.show', [
            'meeting' => $meeting,
            'committee' => $meeting->committee,
            'book' => $meeting->committee?->book,
            'items' => $items,
            'decisions' => $meeting->decisions->sortBy(fn ($d) => [$d->status?->value === 'open' ? 0 : 1, $d->due_date?->timestamp ?? PHP_INT_MAX])->values(),
            'capability' => ['done' => $done, 'max' => $max, 'guided' => $guided->count(), 'pct' => $max > 0 ? (int) round($done / $max * 100) : null],
            'entityNames' => $entityIds->isEmpty() ? collect() : OrganizationEntity::whereIn('id', $entityIds)->pluck('name', 'id'),
            'documentFiles' => $documentFiles,
            'canEdit' => Gate::allows('update', $meeting),
            'checks' => AgendaOutcomeCheck::cases(),
            'statusOptions' => MeetingStatus::cases(),
            'decisionStatusOptions' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], DecisionStatus::cases()),
            'userOptions' => $this->teamUsers($meeting->team_id),
            'entityOptions' => OrganizationEntity::where('team_id', $teamId)->orderBy('name')->limit(300)->get(['id', 'name'])
                ->map(fn ($e) => ['value' => $e->id, 'label' => $e->name])->all(),
            'itemOptions' => $items->map(fn ($i, $k) => ['value' => $i->id, 'label' => ($k + 1) . '. ' . $i->title])->values()->all(),
        ])->layout('platform::layouts.app');
    }

    protected function isGuided(ManagementBookAgendaItem $i): bool
    {
        return filled($i->goal) || filled($i->observation) || filled($i->obstacle)
            || filled($i->local_solution) || filled($i->system_support) || ! empty($i->outcome_checks);
    }

    protected function teamUsers(int $teamId): array
    {
        $ids = DB::table('team_user')->where('team_id', $teamId)->pluck('user_id');

        return \Platform\Core\Models\User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all();
    }

    public static function decisionBadge(?DecisionStatus $s): string
    {
        return match ($s) {
            DecisionStatus::IMPLEMENTED => 'success',
            DecisionStatus::DISCARDED => 'neutral',
            default => 'warning',
        };
    }
}
