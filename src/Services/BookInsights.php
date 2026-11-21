<?php

namespace Platform\ManagementBook\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Platform\ManagementBook\Enums\CommitteeType;
use Platform\ManagementBook\Enums\DecisionStatus;
use Platform\ManagementBook\Enums\ManagementBookStatus;
use Platform\ManagementBook\Enums\MeetingStatus;
use Platform\ManagementBook\Enums\MilestoneStatus;
use Platform\ManagementBook\Enums\RiskLevel;
use Platform\ManagementBook\Enums\RiskStatus;
use Platform\ManagementBook\Enums\StrategicDocumentType;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookDecision;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\ManagementBook\Models\ManagementBookRisk;
use Platform\ManagementBook\Models\ManagementBookStrategicDocument;

/**
 * Kennzahlen für Cockpit (Book-Übersicht) und Dashboard. Liest nur, entscheidet nichts:
 * Zustände werden aus vorhandenen Daten abgeleitet; was nicht erfasst ist, wird als „nicht erfasst“ ausgewiesen.
 */
class BookInsights
{
    /** Zustände einer Regelkreis-Station → Badge-Variante und Label. */
    public const STATES = [
        'done' => ['variant' => 'success', 'label' => 'Erledigt'],
        'active' => ['variant' => 'info', 'label' => 'Läuft'],
        'attention' => ['variant' => 'warning', 'label' => 'Aufmerksamkeit'],
        'open' => ['variant' => 'neutral', 'label' => 'Offen'],
        'untracked' => ['variant' => 'neutral', 'label' => 'Nicht erfasst'],
    ];

    // ── Meilensteine (gleiche Regel wie management-book.transformation_map.GET) ──

    public static function milestoneDueDate(ManagementBookMilestone $m): ?Carbon
    {
        if ($m->target_date) {
            return $m->target_date->copy()->endOfDay();
        }
        if ($m->target_year && $m->target_quarter) {
            return Carbon::create((int) $m->target_year, ((int) $m->target_quarter) * 3, 1)->endOfMonth();
        }
        if ($m->target_year) {
            return Carbon::create((int) $m->target_year, 12, 31)->endOfDay();
        }

        return null;
    }

    public static function milestoneIsOpen(ManagementBookMilestone $m): bool
    {
        return in_array($m->status, [MilestoneStatus::PLANNED, MilestoneStatus::IN_PROGRESS], true);
    }

    public static function milestoneIsOverdue(ManagementBookMilestone $m): bool
    {
        $due = self::milestoneDueDate($m);

        return self::milestoneIsOpen($m) && $due !== null && $due->isPast();
    }

    public static function milestoneWhen(ManagementBookMilestone $m): string
    {
        if ($m->target_date) {
            return $m->target_date->format('d.m.Y');
        }
        if ($m->target_year && $m->target_quarter) {
            return "Q{$m->target_quarter}/{$m->target_year}";
        }

        return $m->target_year ? (string) $m->target_year : 'ohne Termin';
    }

    // ── Ein Book ─────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function forBook(ManagementBook $book): array
    {
        $risks = ManagementBookRisk::query()->where('management_book_id', $book->id)->get();
        $milestones = ManagementBookMilestone::query()->where('management_book_id', $book->id)->with('focusArea:id,title')->get();
        $focusAreaCount = ManagementBookFocusArea::query()->where('management_book_id', $book->id)->count();
        $committees = ManagementBookCommittee::query()->where('management_book_id', $book->id)->get();
        $meetings = ManagementBookMeeting::query()->whereIn('committee_id', $committees->pluck('id'))->with('committee:id,name,type')->get();
        $decisions = ManagementBookDecision::query()->whereIn('meeting_id', $meetings->pluck('id'))->with('ownerUser:id,name', 'ownerAgent:id,name')->get();

        $docs = ManagementBookStrategicDocument::query()->where('management_book_id', $book->id)->active()->get()
            ->keyBy(fn ($d) => $d->type instanceof StrategicDocumentType ? $d->type->value : (string) $d->type);

        $riskData = $this->riskSummary($risks);
        $msData = $this->milestoneSummary($milestones);
        $openDecisions = $decisions->filter(fn ($d) => $d->status === DecisionStatus::OPEN);
        $overdueDecisions = $openDecisions->filter(fn ($d) => $d->due_date && $d->due_date->lt(today()))->sortBy('due_date')->values();

        $nextMeeting = $meetings->filter(fn ($m) => $m->scheduled_at && $m->scheduled_at->gte(now())
            && ! in_array($m->status, [MeetingStatus::CANCELLED, MeetingStatus::HELD], true))
            ->sortBy('scheduled_at')->first();
        $since = now()->subDays(90);
        $recentHeld = $meetings->filter(fn ($m) => $m->status === MeetingStatus::HELD && $m->scheduled_at && $m->scheduled_at->gte($since));

        $chapterTypes = StrategicDocumentType::bookOrder();
        $filled = collect($chapterTypes)->filter(fn ($t) => filled($docs->get($t->value)?->content))->count();

        $insights = [
            'risks' => $riskData,
            'milestones' => $msData,
            'focus_areas' => $focusAreaCount,
            'chapters' => ['filled' => $filled, 'total' => count($chapterTypes)],
            'mission' => $docs->get(StrategicDocumentType::MISSION->value),
            'vision' => $docs->get(StrategicDocumentType::VISION->value),
            'committees' => $committees->count(),
            'next_meeting' => $nextMeeting,
            'meetings_last_90' => $recentHeld->count(),
            'open_decisions' => $openDecisions->count(),
            'overdue_decisions' => $overdueDecisions,
            'review_due' => $book->next_review_at !== null && $book->next_review_at->lte(today()),
        ];
        $insights['cycle'] = $this->cycle($book, $insights, $committees, $meetings, $milestones);

        return $insights;
    }

    /** @param Collection<int,ManagementBookRisk> $risks */
    protected function riskSummary(Collection $risks): array
    {
        $open = $risks->filter(fn ($r) => $r->status !== RiskStatus::CLOSED);
        $byLevel = array_fill_keys(RiskLevel::values(), 0);
        $matrix = [];
        foreach ($open as $r) {
            if ($level = RiskLevel::fromScore($r->score)) {
                $byLevel[$level->value]++;
            }
            if ($r->probability && $r->impact) {
                $matrix[$r->probability][$r->impact] = ($matrix[$r->probability][$r->impact] ?? 0) + 1;
            }
        }

        return [
            'open' => $open->count(),
            'by_level' => $byLevel,
            'unrated' => $open->whereNull('score')->count(),
            'matrix' => $matrix,
            'top' => $open->whereNotNull('score')->sortByDesc('score')->take(5)->values(),
        ];
    }

    /** @param Collection<int,ManagementBookMilestone> $milestones */
    protected function milestoneSummary(Collection $milestones): array
    {
        $relevant = $milestones->filter(fn ($m) => $m->status !== MilestoneStatus::CANCELLED);
        $reached = $relevant->filter(fn ($m) => $m->status === MilestoneStatus::REACHED)->count();
        $overdue = $milestones->filter(fn ($m) => self::milestoneIsOverdue($m))
            ->sortBy(fn ($m) => self::milestoneDueDate($m)?->timestamp ?? PHP_INT_MAX)->values();
        $next = $milestones->filter(fn ($m) => self::milestoneIsOpen($m) && ! self::milestoneIsOverdue($m) && self::milestoneDueDate($m))
            ->sortBy(fn ($m) => self::milestoneDueDate($m)->timestamp)->first();

        return [
            'total' => $relevant->count(),
            'reached' => $reached,
            'reached_percent' => $relevant->count() > 0 ? (int) round($reached / $relevant->count() * 100) : 0,
            'overdue' => $overdue,
            'next' => $next,
        ];
    }

    /**
     * Die acht Stationen des Regelkreises (BROICH.MANAGEMENTMODELL) mit einem aus den Daten abgeleiteten Zustand.
     *
     * @return array<int,array{key:string,name:string,question:string,description:string,state:string,evidence:string,tab:?string}>
     */
    protected function cycle(ManagementBook $book, array $i, Collection $committees, Collection $meetings, Collection $milestones): array
    {
        $byType = fn (CommitteeType $t) => $committees->filter(fn ($c) => $c->type === $t);
        $meetingsOf = fn (Collection $cs) => $meetings->whereIn('committee_id', $cs->pluck('id'));
        $titled = fn (string $needle) => $meetings->filter(fn ($m) => Str::contains(Str::lower($m->title . ' ' . $m->committee?->name), $needle));
        $lastHeld = fn (Collection $ms) => $ms->filter(fn ($m) => $m->status === MeetingStatus::HELD)->sortByDesc('scheduled_at')->first();
        $planned = fn (Collection $ms) => $ms->first(fn ($m) => in_array($m->status, [MeetingStatus::PLANNED, MeetingStatus::PREPARED], true));
        $date = fn ($m) => $m?->scheduled_at?->format('d.m.Y');

        // 1 Strategiemeeting GF
        $strategy = $byType(CommitteeType::STRATEGY_MEETING);
        $sMeet = $meetingsOf($strategy);
        $s1 = match (true) {
            $strategy->isEmpty() => ['untracked', 'Kein Gremium vom Typ Strategie-Meeting angelegt.'],
            (bool) $lastHeld($sMeet) => ['done', 'Zuletzt am ' . $date($lastHeld($sMeet)) . '.'],
            (bool) $planned($sMeet) => ['active', 'Geplant am ' . $date($planned($sMeet)) . '.'],
            default => ['open', 'Gremium angelegt, noch keine Sitzung.'],
        };

        // 2 Strategieworkshop (kein eigener Typ: erkennbar an „Workshop“ in Sitzung oder Gremium)
        $ws = $titled('workshop');
        $s2 = match (true) {
            (bool) $lastHeld($ws) => ['done', 'Workshop am ' . $date($lastHeld($ws)) . '.'],
            (bool) $planned($ws) => ['active', 'Workshop geplant am ' . $date($planned($ws)) . '.'],
            default => ['untracked', 'Keine Sitzung mit „Workshop“ im Titel erfasst.'],
        };

        // 3 Management Book
        $ch = $i['chapters'];
        $s3 = match (true) {
            $ch['filled'] === 0 => ['open', 'Noch keine Kapitel geschrieben.'],
            $ch['filled'] < $ch['total'] => ['active', "{$ch['filled']} von {$ch['total']} Kapiteln geschrieben."],
            default => ['done', 'Alle Kapitel geschrieben.'],
        };

        // 4 Kick-off
        $ko = $titled('kick');
        $s4 = match (true) {
            (bool) $lastHeld($ko) => ['done', 'Kick-off am ' . $date($lastHeld($ko)) . '.'],
            (bool) $planned($ko) => ['active', 'Kick-off geplant am ' . $date($planned($ko)) . '.'],
            default => ['untracked', $book->status === ManagementBookStatus::ACTIVE && $book->activated_at
                ? 'Book aktiv seit ' . $book->activated_at->format('d.m.Y') . ', kein Kick-off-Termin erfasst.'
                : 'Kein Kick-off-Termin erfasst.'],
        };

        // 5 Fokusplanung (Empfehlung 5–7 Fokusräume)
        $fa = $i['focus_areas'];
        $s5 = match (true) {
            $fa === 0 => ['open', 'Noch keine Fokusräume.'],
            $fa >= 5 && $fa <= 7 => ['done', "{$fa} Fokusräume (Empfehlung 5–7)."],
            $fa > 7 => ['attention', "{$fa} Fokusräume – fokussieren heißt verzichten (Empfehlung 5–7)."],
            default => ['active', "{$fa} Fokusräume (Empfehlung 5–7)."],
        };

        // 6 Dezentrale Umsetzung
        $ms = $i['milestones'];
        $s6 = match (true) {
            $ms['total'] === 0 => ['open', 'Noch keine Meilensteine.'],
            $ms['overdue']->isNotEmpty() => ['attention', "{$ms['reached']} von {$ms['total']} Meilensteinen erreicht, {$ms['overdue']->count()} überfällig."],
            $ms['reached'] === $ms['total'] => ['done', 'Alle Meilensteine erreicht.'],
            default => ['active', "{$ms['reached']} von {$ms['total']} Meilensteinen erreicht."],
        };

        // 7 Beobachtung & Lernen (Lenkungsausschuss)
        $steering = $byType(CommitteeType::STEERING_COMMITTEE);
        $stMeet = $meetingsOf($steering);
        $recent = $stMeet->filter(fn ($m) => $m->status === MeetingStatus::HELD && $m->scheduled_at && $m->scheduled_at->gte(now()->subDays(90)));
        $s7 = match (true) {
            $steering->isEmpty() => ['open', 'Kein Lenkungsausschuss angelegt.'],
            $recent->isNotEmpty() => ['active', $recent->count() . ' Sitzung(en) in den letzten 90 Tagen, zuletzt ' . $date($recent->sortByDesc('scheduled_at')->first()) . '.'],
            (bool) $planned($stMeet) => ['attention', 'Keine Sitzung in den letzten 90 Tagen, nächste am ' . $date($planned($stMeet)) . '.'],
            default => ['attention', 'Keine Sitzung in den letzten 90 Tagen.'],
        };

        // 8 Rückkopplung (Review des Books)
        $s8 = match (true) {
            $book->next_review_at === null => ['open', 'Kein Review geplant.'],
            $book->next_review_at->lt(today()) => ['attention', 'Review überfällig seit ' . $book->next_review_at->format('d.m.Y') . '.'],
            default => ['active', 'Nächstes Review am ' . $book->next_review_at->format('d.m.Y') . '.'],
        };

        $def = [
            ['strategy_meeting', 'Strategiemeeting GF', 'Wohin soll sich das Unternehmen langfristig entwickeln?', 'Die Geschäftsführung klärt die Grundsatzfragen: Entwicklungen, Chancen, Risiken, Geschäftsfelder, Ziele.', $s1, 'committees'],
            ['workshop', 'Strategieworkshop', 'Hält diese Richtung, wenn wir sie mit unserer Erfahrung prüfen?', 'Führungskräfte prüfen Annahmen und konfrontieren die Strategie mit der Realität.', $s2, 'committees'],
            ['book', 'Management Book', 'Wohin wollen wir und nach welcher Logik handeln wir?', 'Verdichtet die erarbeitete Orientierung zu Sprache und Gedächtnis der Organisation.', $s3, 'book'],
            ['kickoff', 'Kick-off', 'Was bedeutet das jetzt für das kommende Geschäftsjahr?', 'Übergang von langfristiger Orientierung zu konkretem Fokus: verstehen, übersetzen, fokussieren.', $s4, 'committees'],
            ['focus', 'Fokusplanung', 'Was tun wir jetzt zuerst – und was lassen wir weg?', 'Wenige Themen für das Geschäftsjahr und die Folgejahre – und bewusst, was wir nicht tun.', $s5, 'strategy'],
            ['execution', 'Dezentrale Umsetzung', 'Was ist unser Beitrag zum gemeinsamen Fokus?', 'Bereiche und Profit-Center setzen in eigener Verantwortung um.', $s6, 'strategy'],
            ['learning', 'Beobachtung & Lernen', 'Was lernen wir gerade über unsere Strategie?', 'Der Lenkungsausschuss sieht Fortschritt, Abhängigkeiten und Zielkonflikte.', $s7, 'committees'],
            ['feedback', 'Rückkopplung', 'Was müssen wir an der Strategie selbst ändern?', 'Erkenntnisse fließen in die nächste Strategiearbeit. Ohne diesen Schritt wird Strategie zur Ideologie.', $s8, 'book'],
        ];

        return array_map(fn ($d) => [
            'key' => $d[0], 'name' => $d[1], 'question' => $d[2], 'description' => $d[3],
            'state' => $d[4][0], 'evidence' => $d[4][1], 'tab' => $d[5],
        ], $def);
    }

    // ── Mehrere Books (Dashboard) ───────────────────────────────

    /**
     * @param Collection<int,ManagementBook> $books sichtbare Books
     * @return array<string,mixed>
     */
    /**
     * Kompakte Lage je Book für Listen (Dashboard): kritische/hohe Risiken, nächste Sitzung,
     * offene/überfällige Beschlüsse, überfällige Meilensteine, geschriebene Kapitel.
     *
     * @return array<int, array<string, mixed>>
     */
    private function perBookSummary(Collection $bookIds, Collection $risks, Collection $meetings, Collection $decisions, Collection $overdueMilestones): array
    {
        $chapters = \Platform\ManagementBook\Models\ManagementBookStrategicDocument::query()
            ->whereIn('management_book_id', $bookIds)->where('is_active', true)
            ->selectRaw('management_book_id, count(distinct type) as n')->groupBy('management_book_id')->pluck('n', 'management_book_id');
        $bookOfMeeting = $meetings->mapWithKeys(fn ($m) => [$m->id => $m->committee?->management_book_id]);

        $out = [];
        foreach ($bookIds as $id) {
            $bookRisks = $risks->where('management_book_id', $id);
            $bookMeetings = $meetings->filter(fn ($m) => $m->committee?->management_book_id === $id);
            $bookDecisions = $decisions->filter(fn ($d) => ($bookOfMeeting[$d->meeting_id] ?? null) === $id);
            $out[$id] = [
                'risks_hot' => $bookRisks->filter(fn ($r) => in_array(RiskLevel::fromScore($r->score), [RiskLevel::HIGH, RiskLevel::CRITICAL], true))->count(),
                'risks_critical' => $bookRisks->filter(fn ($r) => RiskLevel::fromScore($r->score) === RiskLevel::CRITICAL)->count(),
                'next_meeting' => $bookMeetings->filter(fn ($m) => $m->scheduled_at && $m->scheduled_at->gte(now())
                    && ! in_array($m->status, [MeetingStatus::CANCELLED, MeetingStatus::HELD], true))->sortBy('scheduled_at')->first(),
                'open_decisions' => $bookDecisions->count(),
                'overdue_decisions' => $bookDecisions->filter(fn ($d) => $d->due_date && $d->due_date->lt(today()))->count(),
                'overdue_milestones' => $overdueMilestones->where('management_book_id', $id)->count(),
                'chapters' => (int) ($chapters[$id] ?? 0),
            ];
        }

        return $out;
    }

    public function forBooks(Collection $books): array
    {
        $bookIds = $books->pluck('id');
        $titles = $books->pluck('title', 'id');

        $risks = ManagementBookRisk::query()->whereIn('management_book_id', $bookIds)
            ->where('status', '!=', RiskStatus::CLOSED->value)->get();
        $committeeIds = ManagementBookCommittee::query()->whereIn('management_book_id', $bookIds)->pluck('id');
        $meetings = ManagementBookMeeting::query()->whereIn('committee_id', $committeeIds)->with('committee:id,name,type,management_book_id')->get();
        $decisions = ManagementBookDecision::query()->whereIn('meeting_id', $meetings->pluck('id'))
            ->where('status', DecisionStatus::OPEN->value)->with('ownerUser:id,name', 'ownerAgent:id,name')->get();
        $milestones = ManagementBookMilestone::query()->whereIn('management_book_id', $bookIds)->with('focusArea:id,title')->get();

        $upcoming = $meetings->filter(fn ($m) => $m->scheduled_at && $m->scheduled_at->gte(now()) && $m->scheduled_at->lte(now()->addDays(30))
            && ! in_array($m->status, [MeetingStatus::CANCELLED, MeetingStatus::HELD], true))->sortBy('scheduled_at')->values();
        $meetingById = $meetings->keyBy('id');
        $overdueDecisions = $decisions->filter(fn ($d) => $d->due_date && $d->due_date->lt(today()));
        $sortedDecisions = $decisions->sortBy(fn ($d) => [
            $d->due_date && $d->due_date->lt(today()) ? 0 : 1,
            $d->due_date?->timestamp ?? PHP_INT_MAX,
        ])->values();
        $overdueMilestones = $milestones->filter(fn ($m) => self::milestoneIsOverdue($m))->values();

        $critical = $risks->filter(fn ($r) => RiskLevel::fromScore($r->score) === RiskLevel::CRITICAL)->count();
        $high = $risks->filter(fn ($r) => RiskLevel::fromScore($r->score) === RiskLevel::HIGH)->count();

        return [
            'active_books' => $books->where('status', ManagementBookStatus::ACTIVE)->count(),
            'risks_critical' => $critical,
            'risks_high' => $high,
            'upcoming_meetings' => $upcoming,
            'open_decisions' => $decisions->count(),
            'overdue_decisions' => $overdueDecisions->count(),
            'decisions' => $sortedDecisions->take(8),
            'meeting_by_id' => $meetingById,
            'overdue_milestones' => $overdueMilestones,
            'top_risks' => $risks->whereNotNull('score')->sortByDesc('score')->take(6)->values(),
            'book_titles' => $titles,
            'committee_books' => $meetings->pluck('committee.management_book_id', 'committee_id'),
            'per_book' => $this->perBookSummary($bookIds, $risks, $meetings, $decisions, $overdueMilestones),
        ];
    }
}
