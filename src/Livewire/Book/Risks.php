<?php

namespace Platform\ManagementBook\Livewire\Book;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\ManagementBook\Enums\RiskAppetite;
use Platform\ManagementBook\Enums\RiskLevel;
use Platform\ManagementBook\Enums\RiskStatus;
use Platform\ManagementBook\Exceptions\ManagementBookException;
use Platform\ManagementBook\Livewire\Concerns\LoadsBook;
use Platform\ManagementBook\Livewire\Concerns\ResolvesTeamFamily;
use Platform\ManagementBook\Models\ManagementBookRisk;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Bereich „Risiken“ eines Management Books: Risikobereitschaft (Risiko-Skala), 5×5-Heatmap,
 * Register nach der Risikomatrix-Vorlage und Anlegen/Bearbeiten. Reine Datenhaltung, keine Ablauflogik.
 */
class Risks extends Component
{
    use LoadsBook, ResolvesTeamFamily;

    // Filter
    public string $statusFilter = '';
    public string $levelFilter = '';
    public string $categoryFilter = '';
    public string $search = '';
    public bool $showClosed = false;
    /** [E, A] des gewählten Heatmap-Felds oder null */
    public ?array $cell = null;

    // Modal
    public bool $showModal = false;
    public ?int $editingId = null;
    public array $form = [];
    public string $entitySearch = '';

    public function filterCell(int $p, int $i): void
    {
        $this->cell = ($this->cell && (int) $this->cell[0] === $p && (int) $this->cell[1] === $i) ? null : [$p, $i];
    }

    public function clearFilters(): void
    {
        $this->reset('statusFilter', 'levelFilter', 'categoryFilter', 'search', 'cell');
    }

    public function setAppetite(?string $value): void
    {
        $book = $this->authorizeEdit();
        if ($value !== null && $value !== '' && ! RiskAppetite::tryFrom($value)) {
            return;
        }
        try {
            app(ManagementBookService::class)->update($book, ['risk_appetite' => $value ?: null]);
        } catch (ManagementBookException $e) {
            session()->flash('risk_error', $e->getMessage());
        }
    }

    // ── Modal ────────────────────────────────────────────────────

    protected function emptyForm(): array
    {
        return [
            'number' => '', 'title' => '', 'description' => '', 'organization_entity_id' => null,
            'probability' => null, 'impact' => null, 'category' => '', 'measures_planned' => '',
            'measures_implemented' => '', 'owner_user_id' => null, 'due_date' => null,
            'status' => RiskStatus::OPEN->value, 'review_date' => null,
        ];
    }

    public function openCreate(): void
    {
        $this->authorizeEdit();
        $this->editingId = null;
        $this->form = $this->emptyForm();
        $this->form['number'] = $this->nextNumber();
        $this->entitySearch = '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $risk = $this->findRisk($id);
        $this->editingId = $risk->id;
        $this->form = array_merge($this->emptyForm(), [
            'number' => $risk->number ?? '',
            'title' => $risk->title,
            'description' => $risk->description ?? '',
            'organization_entity_id' => $risk->organization_entity_id,
            'probability' => $risk->probability,
            'impact' => $risk->impact,
            'category' => $risk->category ?? '',
            'measures_planned' => $risk->measures_planned ?? '',
            'measures_implemented' => $risk->measures_implemented ?? '',
            'owner_user_id' => $risk->owner_user_id,
            'due_date' => $risk->due_date?->toDateString(),
            'status' => $risk->status?->value ?? RiskStatus::OPEN->value,
            'review_date' => $risk->review_date?->toDateString(),
        ]);
        $this->entitySearch = '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $book = $this->authorizeEdit();
        $this->validate([
            'form.number' => 'nullable|string|max:50',
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.organization_entity_id' => 'nullable|integer',
            'form.probability' => 'nullable|integer|min:1|max:5',
            'form.impact' => 'nullable|integer|min:1|max:5',
            'form.category' => 'nullable|string|max:255',
            'form.measures_planned' => 'nullable|string',
            'form.measures_implemented' => 'nullable|string',
            'form.owner_user_id' => 'nullable|integer',
            'form.due_date' => 'nullable|date',
            'form.status' => 'required|in:' . implode(',', RiskStatus::values()),
            'form.review_date' => 'nullable|date',
        ], [], [
            'form.title' => 'Risikobezeichnung', 'form.probability' => 'Eintrittswahrscheinlichkeit', 'form.impact' => 'Auswirkung',
        ]);

        $data = $this->normalizedForm();
        if (! empty($data['organization_entity_id']) && ! OrganizationEntity::whereKey($data['organization_entity_id'])->exists()) {
            $this->addError('form.organization_entity_id', 'Knoten nicht gefunden.');

            return;
        }
        if (! empty($data['owner_user_id']) && ! \Platform\Core\Models\User::whereKey($data['owner_user_id'])->exists()) {
            $this->addError('form.owner_user_id', 'Unbekannter User.');

            return;
        }

        if ($this->editingId) {
            $risk = $this->findRisk($this->editingId);
            $risk->update($data);
        } else {
            ManagementBookRisk::create(array_merge($data, [
                'team_id' => $book->team_id,
                'management_book_id' => $book->id,
                'created_by' => Auth::id(),
            ]));
        }

        $this->showModal = false;
        $this->editingId = null;
    }

    public function closeRisk(int $id): void
    {
        $this->findRisk($id, 'update')->update(['status' => RiskStatus::CLOSED->value]);
        if ($this->editingId === $id) {
            $this->showModal = false;
        }
    }

    public function deleteRisk(int $id): void
    {
        $this->findRisk($id, 'delete')->delete();
        $this->showModal = false;
        $this->editingId = null;
    }

    public function chooseEntity(?int $id): void
    {
        $this->form['organization_entity_id'] = $id;
        $this->entitySearch = '';
    }

    protected function normalizedForm(): array
    {
        $out = [];
        foreach ($this->emptyForm() as $key => $_) {
            $v = $this->form[$key] ?? null;
            $out[$key] = ($v === '' ? null : $v);
        }
        foreach (['probability', 'impact', 'organization_entity_id', 'owner_user_id'] as $k) {
            $out[$k] = $out[$k] === null ? null : (int) $out[$k];
        }
        foreach (['due_date', 'review_date'] as $k) {
            $out[$k] = $out[$k] ? Carbon::parse($out[$k])->toDateString() : null;
        }
        $out['title'] = trim((string) $out['title']);

        return $out;
    }

    protected function findRisk(int $id, string $ability = 'update'): ManagementBookRisk
    {
        $risk = ManagementBookRisk::where('management_book_id', $this->bookId)->findOrFail($id);
        \Illuminate\Support\Facades\Gate::authorize($ability, $risk);

        return $risk;
    }

    protected function nextNumber(): string
    {
        $numbers = ManagementBookRisk::withTrashed()->where('management_book_id', $this->bookId)->pluck('number')
            ->filter(fn ($n) => is_numeric($n))->map(fn ($n) => (int) $n);

        return (string) (($numbers->max() ?? 0) + 1);
    }

    // ── Render ───────────────────────────────────────────────────

    public function render()
    {
        $book = $this->book();
        $all = ManagementBookRisk::query()->where('management_book_id', $book->id)
            ->with(['entity:id,name', 'owner:id,name', 'ownerAgent:id,name'])
            ->get();

        $active = $all->filter(fn ($r) => $r->status !== RiskStatus::CLOSED);

        $counts = [];
        $byLevel = array_fill_keys(RiskLevel::values(), 0);
        foreach ($active as $r) {
            if ($r->probability && $r->impact) {
                $counts[$r->probability][$r->impact] = ($counts[$r->probability][$r->impact] ?? 0) + 1;
            }
            if ($level = $r->level()) {
                $byLevel[$level->value]++;
            }
        }

        $risks = $all
            ->when(! $this->showClosed && $this->statusFilter !== RiskStatus::CLOSED->value, fn ($c) => $c->filter(fn ($r) => $r->status !== RiskStatus::CLOSED))
            ->when($this->statusFilter !== '', fn ($c) => $c->filter(fn ($r) => $r->status?->value === $this->statusFilter))
            ->when($this->levelFilter !== '', fn ($c) => $c->filter(fn ($r) => $r->level()?->value === $this->levelFilter))
            ->when($this->categoryFilter !== '', fn ($c) => $c->filter(fn ($r) => $r->category === $this->categoryFilter))
            ->when($this->cell, fn ($c) => $c->filter(fn ($r) => (int) $r->probability === (int) $this->cell[0] && (int) $r->impact === (int) $this->cell[1]))
            ->when(trim($this->search) !== '', function ($c) {
                $term = mb_strtolower(trim($this->search));

                return $c->filter(fn ($r) => str_contains(mb_strtolower(implode(' ', [
                    $r->number, $r->title, $r->description, $r->measures_planned, $r->measures_implemented, $r->category,
                ])), $term));
            })
            ->sortBy([
                fn ($a, $b) => ($b->score ?? -1) <=> ($a->score ?? -1),
                fn ($a, $b) => strnatcmp((string) $a->number, (string) $b->number),
            ])
            ->values();

        $categories = $all->pluck('category')->filter()->unique()->sort()->values();

        // Modal-Daten
        $preview = null;
        $entityOptions = collect();
        $selectedEntity = null;
        $members = collect();
        if ($this->showModal) {
            $p = (int) ($this->form['probability'] ?? 0);
            $i = (int) ($this->form['impact'] ?? 0);
            if ($p && $i) {
                $preview = ['score' => $p * $i, 'level' => RiskLevel::fromScore($p * $i)];
            }
            $teamIds = $this->teamIdsFor($book);
            $selectedEntity = ! empty($this->form['organization_entity_id'])
                ? OrganizationEntity::find((int) $this->form['organization_entity_id'])
                : null;
            // Vorschläge erst beim Öffnen sichtbar (Alpine); aktive Knoten zuerst, Knotentyp als Hinweis, max. 8
            $entityOptions = OrganizationEntity::query()
                ->whereIn('team_id', $teamIds)
                ->with('type:id,name')
                ->when(trim($this->entitySearch) !== '', fn ($q) => $q->where('name', 'like', '%' . trim($this->entitySearch) . '%'))
                ->orderByDesc('is_active')->orderBy('name')->limit(8)->get(['id', 'name', 'is_active', 'entity_type_id']);
            $members = \Platform\Core\Models\User::query()
                ->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teamIds))
                ->orderBy('name')->get(['id', 'name']);
        }

        return view('management-book::livewire.book.risks', [
            'book' => $book,
            'canEdit' => $this->canEdit(),
            'risks' => $risks,
            'totalActive' => $active->count(),
            'totalAll' => $all->count(),
            'closedCount' => $all->count() - $active->count(),
            'counts' => $counts,
            'byLevel' => $byLevel,
            'unrated' => $active->filter(fn ($r) => $r->score === null)->count(),
            'categories' => $categories,
            'appetite' => $book->risk_appetite,
            'preview' => $preview,
            'entityOptions' => $entityOptions,
            'selectedEntity' => $selectedEntity,
            'members' => $members,
            // Public State explizit (auch für direktes Rendern ohne Livewire-Lifecycle)
            'cell' => $this->cell,
            'levelFilter' => $this->levelFilter,
            'showModal' => $this->showModal,
            'editingId' => $this->editingId,
            'form' => $this->form,
            'hasFilters' => $this->statusFilter !== '' || $this->levelFilter !== '' || $this->categoryFilter !== '' || trim($this->search) !== '' || $this->cell,
        ]);
    }

    /** @return int[] */
    protected function teamIdsFor($book): array
    {
        try {
            $ids = $this->relevantTeamIds(Auth::user());
        } catch (\Throwable) {
            $ids = [];
        }

        return $ids !== [] ? $ids : [$book->team_id];
    }
}
