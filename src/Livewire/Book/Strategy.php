<?php

namespace Platform\ManagementBook\Livewire\Book;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\ManagementBook\Enums\MilestoneStatus;
use Platform\ManagementBook\Livewire\Concerns\LoadsBook;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Models\ManagementBookForecast;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\ManagementBook\Models\ManagementBookObstacle;
use Platform\ManagementBook\Models\ManagementBookVisionImage;
use Platform\ManagementBook\Tools\Concerns\HandlesTransformationMap;

/**
 * Bereich „Strategie“ eines Management Books: Fokusräume (Zielbilder / Hindernisse / Meilensteine),
 * Transformation Map (Meilensteine als Waypoints über Jahre × Quartale) und Regnose/Forecasts (versioniert).
 * Reine Datenpflege – Überfälligkeit und nächster Waypoint folgen derselben Definition wie transformation_map.GET.
 */
class Strategy extends Component
{
    use LoadsBook;
    use HandlesTransformationMap;

    /** Unterbereich: focus | map | regnose */
    #[Url(as: 'view')]
    public string $section = 'focus';

    // Fokusraum-Dialog
    public bool $showFocusArea = false;
    public ?int $editingFocusAreaId = null;
    public array $faForm = [];

    // Element-Dialog (Zielbild / Hindernis / Meilenstein)
    public bool $showItem = false;
    public string $itemKind = 'milestone';
    public ?int $itemFocusAreaId = null;
    public ?int $editingItemId = null;
    public array $itemForm = [];

    // Forecast-Dialoge
    public bool $showForecast = false;
    public ?int $editingForecastId = null;
    public array $forecastForm = [];
    public bool $showVersion = false;
    public ?int $versionForecastId = null;
    public array $versionForm = ['content' => '', 'change_note' => ''];
    public ?int $historyForecastId = null;

    public const KINDS = [
        'vision_image' => ['label' => 'Zielbild', 'plural' => 'Zielbilder', 'class' => ManagementBookVisionImage::class, 'question' => 'central_question_vision_images',
            'default_question' => 'Woran erkennen wir, dass wir angekommen sind?', 'icon' => 'heroicon-o-eye'],
        'obstacle' => ['label' => 'Hindernis', 'plural' => 'Hindernisse', 'class' => ManagementBookObstacle::class, 'question' => 'central_question_obstacles',
            'default_question' => 'Was steht dem Zielbild heute im Weg?', 'icon' => 'heroicon-o-no-symbol'],
        'milestone' => ['label' => 'Meilenstein', 'plural' => 'Meilensteine', 'class' => ManagementBookMilestone::class, 'question' => 'central_question_milestones',
            'default_question' => 'Welche Etappen machen den Weg zurück in die Gegenwart sichtbar?', 'icon' => 'heroicon-o-flag'],
    ];

    public function setSection(string $section): void
    {
        $this->section = in_array($section, ['focus', 'map', 'regnose'], true) ? $section : 'focus';
    }

    // ── Fokusräume ───────────────────────────────────────────────

    public function openFocusArea(?int $id = null): void
    {
        $this->authorizeEdit();
        $fa = $id ? $this->findFocusAreaOfBook($id) : null;
        $this->editingFocusAreaId = $fa?->id;
        $this->faForm = [
            'title' => $fa?->title ?? '',
            'description' => $fa?->description ?? '',
            'forecast_id' => $fa?->forecast_id,
            'central_question_vision_images' => $fa?->central_question_vision_images ?? '',
            'central_question_obstacles' => $fa?->central_question_obstacles ?? '',
            'central_question_milestones' => $fa?->central_question_milestones ?? '',
        ];
        $this->resetValidation();
        $this->showFocusArea = true;
    }

    public function saveFocusArea(): void
    {
        $book = $this->authorizeEdit();
        $this->validate([
            'faForm.title' => 'required|string|max:255',
            'faForm.description' => 'nullable|string',
            'faForm.forecast_id' => 'nullable|integer',
            'faForm.central_question_vision_images' => 'nullable|string',
            'faForm.central_question_obstacles' => 'nullable|string',
            'faForm.central_question_milestones' => 'nullable|string',
        ], [], ['faForm.title' => 'Titel']);

        $forecastId = ! empty($this->faForm['forecast_id']) ? (int) $this->faForm['forecast_id'] : null;
        if ($forecastId && ! ManagementBookForecast::where('management_book_id', $book->id)->whereKey($forecastId)->exists()) {
            $this->addError('faForm.forecast_id', 'Forecast gehört nicht zu diesem Book.');

            return;
        }

        $data = [
            'title' => trim($this->faForm['title']),
            'description' => $this->blankToNull($this->faForm['description'] ?? null),
            'forecast_id' => $forecastId,
            'central_question_vision_images' => $this->blankToNull($this->faForm['central_question_vision_images'] ?? null),
            'central_question_obstacles' => $this->blankToNull($this->faForm['central_question_obstacles'] ?? null),
            'central_question_milestones' => $this->blankToNull($this->faForm['central_question_milestones'] ?? null),
        ];

        if ($this->editingFocusAreaId) {
            $this->findFocusAreaOfBook($this->editingFocusAreaId)->update($data);
        } else {
            ManagementBookFocusArea::create($data + [
                'team_id' => $book->team_id,
                'management_book_id' => $book->id,
                'order' => 1 + (int) ManagementBookFocusArea::where('management_book_id', $book->id)->max('order'),
                'created_by' => Auth::id(),
            ]);
        }
        $this->showFocusArea = false;
    }

    public function deleteFocusArea(int $id): void
    {
        $this->authorizeEdit();
        $this->findFocusAreaOfBook($id)->delete();
    }

    public function moveFocusArea(int $id, int $direction): void
    {
        $book = $this->authorizeEdit();
        $this->swapOrder(ManagementBookFocusArea::where('management_book_id', $book->id)->orderBy('order')->orderBy('id')->get(), $id, $direction);
    }

    // ── Elemente der drei Spalten ────────────────────────────────

    public function openItem(string $kind, int $focusAreaId, ?int $id = null): void
    {
        $this->authorizeEdit();
        abort_unless(isset(self::KINDS[$kind]), 404);
        $fa = $this->findFocusAreaOfBook($focusAreaId);
        $item = $id ? $this->findItem($kind, $id) : null;

        $this->itemKind = $kind;
        $this->itemFocusAreaId = $item?->focus_area_id ?? $fa->id;
        $this->editingItemId = $item?->id;
        $this->itemForm = [
            'title' => $item?->title ?? '',
            'description' => $item?->description ?? '',
            'central_question' => $item?->central_question ?? '',
            'focus_area_id' => $item?->focus_area_id ?? $fa->id,
            'target_year' => $item?->target_year,
            'target_quarter' => $item?->target_quarter,
            'target_date' => $item?->target_date?->toDateString(),
            'status' => $item?->status?->value ?? MilestoneStatus::PLANNED->value,
        ];
        $this->resetValidation();
        $this->showItem = true;
    }

    public function saveItem(): void
    {
        $book = $this->authorizeEdit();
        $kind = $this->itemKind;
        abort_unless(isset(self::KINDS[$kind]), 404);

        $rules = [
            'itemForm.title' => 'required|string|max:255',
            'itemForm.description' => 'nullable|string',
            'itemForm.central_question' => 'nullable|string',
            'itemForm.focus_area_id' => 'required|integer',
        ];
        if ($kind === 'milestone') {
            $rules += [
                'itemForm.target_year' => 'nullable|integer|min:1900|max:2200',
                'itemForm.target_quarter' => 'nullable|integer|min:1|max:4',
                'itemForm.target_date' => 'nullable|date',
                'itemForm.status' => 'required|in:' . implode(',', MilestoneStatus::values()),
            ];
        }
        $this->validate($rules, [], [
            'itemForm.title' => 'Titel', 'itemForm.target_year' => 'Zieljahr', 'itemForm.target_quarter' => 'Quartal', 'itemForm.target_date' => 'Datum',
        ]);

        $fa = $this->findFocusAreaOfBook((int) $this->itemForm['focus_area_id']);
        $data = [
            'focus_area_id' => $fa->id,
            'title' => trim($this->itemForm['title']),
            'description' => $this->blankToNull($this->itemForm['description'] ?? null),
            'central_question' => $this->blankToNull($this->itemForm['central_question'] ?? null),
        ];
        if ($kind === 'milestone') {
            $data += [
                'target_year' => $this->blankToNull($this->itemForm['target_year'] ?? null),
                'target_quarter' => $this->blankToNull($this->itemForm['target_quarter'] ?? null),
                'target_date' => $this->blankToNull($this->itemForm['target_date'] ?? null),
                'status' => $this->itemForm['status'],
            ];
        }

        $class = self::KINDS[$kind]['class'];
        if ($this->editingItemId) {
            $this->findItem($kind, $this->editingItemId)->update($data);
        } else {
            $class::create($data + [
                'order' => 1 + (int) $class::where('focus_area_id', $fa->id)->max('order'),
                'created_by' => Auth::id(),
            ]);
        }
        $this->showItem = false;
    }

    public function deleteItem(string $kind, int $id): void
    {
        $this->authorizeEdit();
        $this->findItem($kind, $id)->delete();
        $this->showItem = false;
    }

    public function moveItem(string $kind, int $id, int $direction): void
    {
        $this->authorizeEdit();
        $item = $this->findItem($kind, $id);
        $class = self::KINDS[$kind]['class'];
        $this->swapOrder($class::where('focus_area_id', $item->focus_area_id)->orderBy('order')->orderBy('id')->get(), $id, $direction);
    }

    /** Status eines Meilensteins setzen; reached_at folgt dem Status (Model-Hook). */
    public function setMilestoneStatus(int $id, string $status): void
    {
        $this->authorizeEdit();
        $enum = MilestoneStatus::tryFrom($status);
        abort_unless($enum, 422);
        $this->findItem('milestone', $id)->update(['status' => $enum]);
    }

    // ── Forecasts / Regnose ──────────────────────────────────────

    public function openForecast(?int $id = null): void
    {
        $this->authorizeEdit();
        $f = $id ? $this->findForecast($id) : null;
        $this->editingForecastId = $f?->id;
        $this->forecastForm = [
            'title' => $f?->title ?? '',
            'target_year' => $f?->target_date?->year ?? ($this->book()->horizon_year ?: null),
            'content' => '',
            'change_note' => '',
        ];
        $this->resetValidation();
        $this->showForecast = true;
    }

    public function saveForecast(): void
    {
        $book = $this->authorizeEdit();
        $this->validate([
            'forecastForm.title' => 'required|string|max:255',
            'forecastForm.target_year' => 'nullable|integer|min:1900|max:2200',
            'forecastForm.content' => 'nullable|string',
        ], [], ['forecastForm.title' => 'Titel', 'forecastForm.target_year' => 'Zieljahr']);

        $targetDate = ! empty($this->forecastForm['target_year'])
            ? Carbon::create((int) $this->forecastForm['target_year'], 12, 31)->toDateString()
            : null;

        if ($this->editingForecastId) {
            $this->findForecast($this->editingForecastId)->update([
                'title' => trim($this->forecastForm['title']),
                'target_date' => $targetDate,
            ]);
        } else {
            $forecast = ManagementBookForecast::create([
                'team_id' => $book->team_id,
                'management_book_id' => $book->id,
                'title' => trim($this->forecastForm['title']),
                'target_date' => $targetDate,
                'created_by' => Auth::id(),
            ]);
            $content = trim((string) ($this->forecastForm['content'] ?? ''));
            if ($content !== '') {
                $forecast->createNewVersion($content, 'Erste Fassung', Auth::id());
            }
        }
        $this->showForecast = false;
    }

    public function deleteForecast(int $id): void
    {
        $this->authorizeEdit();
        $forecast = $this->findForecast($id);
        ManagementBookFocusArea::where('forecast_id', $forecast->id)->update(['forecast_id' => null]);
        $forecast->delete();
    }

    public function openVersion(int $forecastId): void
    {
        $this->authorizeEdit();
        $f = $this->findForecast($forecastId);
        $this->versionForecastId = $f->id;
        $this->versionForm = ['content' => (string) $f->content, 'change_note' => ''];
        $this->resetValidation();
        $this->showVersion = true;
    }

    public function saveVersion(): void
    {
        $this->authorizeEdit();
        $this->validate([
            'versionForm.content' => 'required|string',
            'versionForm.change_note' => 'nullable|string|max:255',
        ], [], ['versionForm.content' => 'Inhalt']);

        $f = $this->findForecast((int) $this->versionForecastId);
        $f->createNewVersion((string) $this->versionForm['content'], $this->blankToNull($this->versionForm['change_note'] ?? null), Auth::id());
        $this->showVersion = false;
    }

    public function toggleHistory(int $forecastId): void
    {
        $this->historyForecastId = $this->historyForecastId === $forecastId ? null : $forecastId;
    }

    // ── Render ───────────────────────────────────────────────────

    public function render()
    {
        $book = $this->book();

        $focusAreas = ManagementBookFocusArea::where('management_book_id', $book->id)
            ->with([
                'forecast:id,title',
                'visionImages' => fn ($q) => $q->orderBy('order')->orderBy('id'),
                'obstacles' => fn ($q) => $q->orderBy('order')->orderBy('id'),
                'milestones' => fn ($q) => $q->orderBy('target_year')->orderBy('target_quarter')->orderBy('order')->orderBy('id'),
            ])
            ->orderBy('order')->orderBy('id')->get();

        $forecasts = ManagementBookForecast::where('management_book_id', $book->id)
            ->with(['currentVersion', 'versions', 'focusAreas:id,title,forecast_id'])
            ->orderBy('target_date')->orderBy('id')->get();

        $map = $this->buildMap($focusAreas, $book->horizon_year);

        return view('management-book::livewire.book.strategy', [
            'book' => $book,
            'canEdit' => $this->canEdit(),
            'focusAreas' => $focusAreas,
            'forecasts' => $forecasts,
            'map' => $map,
            'kinds' => self::KINDS,
            'recommendedMax' => ManagementBookFocusArea::RECOMMENDED_MAX,
            'statusOptions' => array_map(fn (MilestoneStatus $s) => ['value' => $s->value, 'label' => $s->label()], MilestoneStatus::cases()),
            'focusAreaOptions' => $focusAreas->map(fn ($fa) => ['value' => $fa->id, 'label' => $fa->title])->values()->all(),
            'forecastOptions' => $forecasts->map(fn ($f) => ['value' => $f->id, 'label' => $f->title . ($f->target_date ? ' (' . $f->target_date->year . ')' : '')])->values()->all(),
            'quarterOptions' => [['value' => 1, 'label' => 'Q1'], ['value' => 2, 'label' => 'Q2'], ['value' => 3, 'label' => 'Q3'], ['value' => 4, 'label' => 'Q4']],
        ]);
    }

    /**
     * Transformation Map: Jahre × Quartale, Bahnen je Fokusraum. Überfällig = offen (geplant/in Arbeit) und
     * Zielzeitraum vorbei (gleiche Definition wie management-book.transformation_map.GET).
     */
    private function buildMap(Collection $focusAreas, ?int $horizonYear): array
    {
        $now = now();
        $milestones = $focusAreas->flatMap->milestones;

        $overdue = [];
        $next = null;
        $sorted = $milestones->sort(fn ($a, $b) => [$a->target_year === null ? 1 : 0, $a->target_year, $a->target_quarter ?? 5, $a->target_date?->toDateString() ?? '9999', $a->order, $a->id]
            <=> [$b->target_year === null ? 1 : 0, $b->target_year, $b->target_quarter ?? 5, $b->target_date?->toDateString() ?? '9999', $b->order, $b->id])->values();
        foreach ($sorted as $m) {
            $due = $this->waypointDueDate($m);
            $isOverdue = $this->isOpenWaypoint($m) && $due !== null && $due->lt($now);
            if ($isOverdue) {
                $overdue[$m->id] = true;
            }
            if ($next === null && $this->isOpenWaypoint($m) && ! $isOverdue) {
                $next = $m;
            }
        }

        $years = $milestones->pluck('target_year')->filter()->map(fn ($y) => (int) $y);
        $from = min($years->min() ?? (int) $now->year, (int) $now->year);
        $to = max($years->max() ?? (int) $now->year, $horizonYear ?: (int) $now->year, $from);
        if ($to - $from > 15) {
            $to = $from + 15;
        }

        $cells = [];
        $undated = [];
        foreach ($milestones as $m) {
            if ($m->target_year) {
                $cells[$m->focus_area_id][(int) $m->target_year][(int) ($m->target_quarter ?: 4)][] = $m;
            } else {
                $undated[$m->focus_area_id][] = $m;
            }
        }

        $relevant = $milestones->reject(fn ($m) => $m->status === MilestoneStatus::CANCELLED);
        $reached = $relevant->where('status', MilestoneStatus::REACHED)->count();

        return [
            'years' => range($from, $to),
            'cells' => $cells,
            'undated' => $undated,
            'overdue' => $overdue,
            'next' => $next,
            'total' => $milestones->count(),
            'reached' => $reached,
            'reached_percent' => $relevant->count() ? (int) round($reached / $relevant->count() * 100) : 0,
            'current' => ['year' => (int) $now->year, 'quarter' => (int) $now->quarter],
            'has_undated' => $undated !== [],
        ];
    }

    // ── Helfer ───────────────────────────────────────────────────

    private function findFocusAreaOfBook(int $id): ManagementBookFocusArea
    {
        return ManagementBookFocusArea::where('management_book_id', $this->bookId)->findOrFail($id);
    }

    private function findItem(string $kind, int $id)
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        $class = self::KINDS[$kind]['class'];

        return $class::where('management_book_id', $this->bookId)->findOrFail($id);
    }

    private function findForecast(int $id): ManagementBookForecast
    {
        return ManagementBookForecast::where('management_book_id', $this->bookId)->findOrFail($id);
    }

    /** Tauscht die Reihenfolge eines Elements mit seinem Nachbarn und normalisiert order auf 1..n. */
    private function swapOrder(Collection $items, int $id, int $direction): void
    {
        $list = $items->values()->all();
        $idx = collect($list)->search(fn ($m) => $m->id === $id);
        $target = $idx === false ? false : $idx + ($direction < 0 ? -1 : 1);
        if ($idx === false || $target < 0 || $target >= count($list)) {
            return;
        }
        [$list[$idx], $list[$target]] = [$list[$target], $list[$idx]];
        foreach ($list as $i => $m) {
            if ((int) $m->order !== $i + 1) {
                $m->update(['order' => $i + 1]);
            }
        }
    }

    private function blankToNull(mixed $value): mixed
    {
        return ($value === '' || $value === null) ? null : $value;
    }
}
