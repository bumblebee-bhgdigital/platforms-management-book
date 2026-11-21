<?php

namespace Platform\ManagementBook\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Models\User;
use Platform\ManagementBook\Enums\ManagementBookStatus;
use Platform\ManagementBook\Enums\StrategicDocumentType;
use Platform\ManagementBook\Exceptions\ManagementBookException;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Models\ManagementBookForecast;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\ManagementBook\Models\ManagementBookMilestoneContribution;
use Platform\ManagementBook\Models\ManagementBookObstacle;
use Platform\ManagementBook\Models\ManagementBookStrategicDocument;
use Platform\ManagementBook\Models\ManagementBookVisionImage;

/**
 * Übernimmt den Strategie-Layer der Organisation (organization_strategic_documents, _forecasts(+versions),
 * _focus_areas, _vision_images, _obstacles, _milestones, _milestone_contributions) eines Knotens in ein Management Book.
 *
 * - Liest serverseitig direkt aus den Organisations-Tabellen (nur lesen, nichts wird dort verändert).
 * - Übernimmt die UUIDs der Quelle: dadurch idempotent (bereits übernommene Datensätze werden übersprungen)
 *   und jederzeit rückverfolgbar.
 * - Texte 1:1, Forecast-Versionen vollständig in ihrer Reihenfolge, Fokusräume auch ohne Forecast.
 * - Meilensteine starten als „geplant“ (die Organisation kennt keinen Status).
 * - Probelauf (dryRun): alles in einer Transaktion, die am Ende zurückgerollt wird.
 */
class OrganizationStrategyImporter
{
    /** @return array<string,mixed> */
    public function import(int $entityId, User $user, ?ManagementBook $book = null, ?array $onlyFocusAreaIds = null, bool $dryRun = false): array
    {
        if (! Schema::hasTable('organization_focus_areas')) {
            throw new ManagementBookException('Der Strategie-Layer der Organisation ist auf dieser Instanz nicht vorhanden.');
        }
        $entity = DB::table('organization_entities')->where('id', $entityId)->first();
        if (! $entity) {
            throw new ManagementBookException("Knoten {$entityId} nicht gefunden.");
        }

        $src = $this->readSource($entityId, $onlyFocusAreaIds);
        $report = [
            'entity' => ['id' => $entityId, 'name' => $entity->name],
            'dry_run' => $dryRun,
            'source' => [
                'documents' => count($src['documents']),
                'forecasts' => count($src['forecasts']),
                'forecast_versions' => array_sum(array_map('count', $src['forecast_versions'])),
                'focus_areas' => count($src['focus_areas']),
                'vision_images' => count($src['vision_images']),
                'obstacles' => count($src['obstacles']),
                'milestones' => count($src['milestones']),
                'contributions' => count($src['contributions']),
            ],
            'created' => array_fill_keys(['book', 'documents', 'forecasts', 'forecast_versions', 'focus_areas', 'vision_images', 'obstacles', 'milestones', 'contributions'], 0),
            'skipped' => [],
        ];

        DB::beginTransaction();
        try {
            $book = $book ?? $this->createBook($entity, $user, $src);
            $report['book'] = ['id' => $book->id, 'title' => $book->title];
            $createdBook = $book->wasRecentlyCreated;
            $report['created']['book'] = $createdBook ? 1 : 0;

            // Mission/Vision (nur beim vollen Import, nicht beim Nachtragen einzelner Fokusräume)
            if ($onlyFocusAreaIds === null) {
                foreach ($src['documents'] as $d) {
                    $type = StrategicDocumentType::tryFrom((string) $d->type);
                    if (! $type) {
                        $report['skipped'][] = "Dokument #{$d->id}: unbekannter Typ {$d->type}";
                        continue;
                    }
                    if (ManagementBookStrategicDocument::withTrashed()->where('uuid', $d->uuid)->exists()) {
                        continue;
                    }
                    ManagementBookStrategicDocument::query()->where('management_book_id', $book->id)->where('type', $type->value)->update(['is_active' => false]);
                    ManagementBookStrategicDocument::create([
                        'uuid' => $d->uuid,
                        'team_id' => $book->team_id,
                        'management_book_id' => $book->id,
                        'type' => $type,
                        'title' => $d->title,
                        'content' => $d->content,
                        'is_active' => true,
                        'valid_from' => $d->valid_from,
                        'change_note' => trim('Übernommen aus Organisation (Version ' . $d->version . ')' . ($d->change_note ? ' – ' . $d->change_note : '')),
                        'created_by' => $user->id,
                    ]);
                    $report['created']['documents']++;
                }
            }

            // Forecasts inkl. aller Versionen
            $forecastMap = [];
            foreach ($src['forecasts'] as $f) {
                $existing = ManagementBookForecast::withTrashed()->where('uuid', $f->uuid)->first();
                if ($existing) {
                    $forecastMap[$f->id] = $existing->id;
                    continue;
                }
                $mf = ManagementBookForecast::create([
                    'uuid' => $f->uuid,
                    'team_id' => $book->team_id,
                    'management_book_id' => $book->id,
                    'title' => $f->title,
                    'target_date' => $f->target_date,
                    'content' => $f->content,
                    'created_by' => $user->id,
                ]);
                $versions = $src['forecast_versions'][$f->id] ?? [];
                foreach ($versions as $v) {
                    $mf->createNewVersion((string) $v->content, $v->change_note ?: "Übernommen aus Organisation (Version {$v->version})", $user->id);
                    $report['created']['forecast_versions']++;
                }
                if ($versions === [] && filled($f->content)) {
                    $mf->createNewVersion((string) $f->content, 'Übernommen aus Organisation', $user->id);
                    $report['created']['forecast_versions']++;
                }
                $forecastMap[$f->id] = $mf->id;
                $report['created']['forecasts']++;
            }

            // Fokusräume
            $faMap = [];
            foreach ($src['focus_areas'] as $fa) {
                $existing = ManagementBookFocusArea::withTrashed()->where('uuid', $fa->uuid)->first();
                if ($existing) {
                    $faMap[$fa->id] = $existing->id;
                    continue;
                }
                $forecastId = $fa->forecast_id ? ($forecastMap[$fa->forecast_id] ?? $this->bookForecastByOrgId($book, $fa->forecast_id)) : null;
                $faMap[$fa->id] = ManagementBookFocusArea::create([
                    'uuid' => $fa->uuid,
                    'team_id' => $book->team_id,
                    'management_book_id' => $book->id,
                    'forecast_id' => $forecastId,
                    'title' => $fa->title,
                    'description' => $fa->description,
                    'content' => $fa->content,
                    'central_question_vision_images' => $fa->central_question_vision_images,
                    'central_question_obstacles' => $fa->central_question_obstacles,
                    'central_question_milestones' => $fa->central_question_milestones,
                    'order' => $fa->order ?? 0,
                    'created_by' => $user->id,
                ])->id;
                $report['created']['focus_areas']++;
            }

            // Zielbilder, Hindernisse, Meilensteine
            $msMap = [];
            foreach (['vision_images' => ManagementBookVisionImage::class, 'obstacles' => ManagementBookObstacle::class, 'milestones' => ManagementBookMilestone::class] as $kind => $class) {
                foreach ($src[$kind] as $it) {
                    $existing = $class::withTrashed()->where('uuid', $it->uuid)->first();
                    if ($existing) {
                        if ($kind === 'milestones') {
                            $msMap[$it->id] = $existing->id;
                        }
                        continue;
                    }
                    if (! isset($faMap[$it->focus_area_id])) {
                        $report['skipped'][] = "{$kind} #{$it->id}: Fokusraum #{$it->focus_area_id} nicht übernommen";
                        continue;
                    }
                    $attrs = [
                        'uuid' => $it->uuid,
                        'team_id' => $book->team_id,
                        'focus_area_id' => $faMap[$it->focus_area_id],
                        'title' => $it->title,
                        'description' => $it->description,
                        'central_question' => $it->central_question,
                        'order' => $it->order ?? 0,
                        'created_by' => $user->id,
                    ];
                    if ($kind === 'milestones') {
                        $attrs += [
                            'target_date' => $it->target_date,
                            'target_year' => $it->target_year,
                            'target_quarter' => $it->target_quarter,
                        ];
                    }
                    $model = $class::create($attrs);
                    if ($kind === 'milestones') {
                        $msMap[$it->id] = $model->id;
                    }
                    $report['created'][$kind]++;
                }
            }

            // Beiträge aus dem OKR-Modul (nicht mehr existierende Objekte werden übersprungen)
            foreach ($src['contributions'] as $c) {
                if (! isset($msMap[$c->milestone_id])) {
                    $report['skipped'][] = "Beitrag #{$c->id}: Meilenstein #{$c->milestone_id} nicht übernommen";
                    continue;
                }
                if (ManagementBookMilestoneContribution::query()->where('uuid', $c->uuid)->exists()) {
                    continue;
                }
                $alias = ManagementBookMilestoneContribution::resolveType((string) $c->linkable_type);
                if (! $alias) {
                    $report['skipped'][] = "Beitrag #{$c->id}: Objekttyp {$c->linkable_type} nicht registriert";
                    continue;
                }
                try {
                    DB::transaction(function () use ($c, $alias, $msMap, $book, $user) {
                        ManagementBookMilestoneContribution::create([
                            'uuid' => $c->uuid,
                            'team_id' => $book->team_id,
                            'milestone_id' => $msMap[$c->milestone_id],
                            'linkable_type' => $alias,
                            'linkable_id' => $c->linkable_id,
                            'weight' => $c->weight,
                            'created_by_user_id' => $user->id,
                        ]);
                    });
                    $report['created']['contributions']++;
                } catch (\Throwable $e) {
                    $report['skipped'][] = "Beitrag #{$c->id} ({$c->linkable_type} #{$c->linkable_id}): " . $e->getMessage();
                }
            }

            if ($dryRun) {
                DB::rollBack();
                unset($report['book']['id']);
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $report;
    }

    /** @return array<string,mixed> */
    private function readSource(int $entityId, ?array $onlyFocusAreaIds): array
    {
        $alive = fn (string $table) => DB::table($table)->when(Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));

        $focusAreas = $alive('organization_focus_areas')->where('entity_id', $entityId)
            ->when($onlyFocusAreaIds !== null, fn ($q) => $q->whereIn('id', $onlyFocusAreaIds))
            ->orderBy('order')->orderBy('id')->get();
        $faIds = $focusAreas->pluck('id')->all();

        $forecastIds = $onlyFocusAreaIds === null
            ? $alive('organization_forecasts')->where('entity_id', $entityId)->pluck('id')->all()
            : $focusAreas->pluck('forecast_id')->filter()->unique()->values()->all();
        $forecasts = $alive('organization_forecasts')->whereIn('id', $forecastIds)->orderBy('target_date')->orderBy('id')->get();
        $versions = Schema::hasTable('organization_forecast_versions')
            ? $alive('organization_forecast_versions')->whereIn('forecast_id', $forecastIds)->orderBy('version')->orderBy('id')->get()->groupBy('forecast_id')->map->all()->all()
            : [];

        $items = fn (string $table) => $alive($table)->whereIn('focus_area_id', $faIds)->orderBy('focus_area_id')->orderBy('order')->orderBy('id')->get();
        $milestones = $items('organization_milestones');

        $contributions = Schema::hasTable('organization_milestone_contributions')
            ? DB::table('organization_milestone_contributions')->whereIn('milestone_id', $milestones->pluck('id'))
                ->when(Schema::hasColumn('organization_milestone_contributions', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->orderBy('id')->get()
            : collect();

        return [
            'documents' => $onlyFocusAreaIds === null
                ? $alive('organization_strategic_documents')->where('entity_id', $entityId)->where('is_active', true)->orderBy('type')->get()->all()
                : [],
            'forecasts' => $forecasts->all(),
            'forecast_versions' => $versions,
            'focus_areas' => $focusAreas->all(),
            'vision_images' => $items('organization_vision_images')->all(),
            'obstacles' => $items('organization_obstacles')->all(),
            'milestones' => $milestones->all(),
            'contributions' => $contributions->all(),
        ];
    }

    private function createBook(object $entity, User $user, array $src): ManagementBook
    {
        $service = app(ManagementBookService::class);
        if ($existing = $service->findActiveForNode((int) $entity->id)) {
            throw new ManagementBookException("Knoten {$entity->name} hat bereits ein aktives Book (#{$existing->id}). Für einen Nachtrag management_book_id angeben.");
        }
        $years = collect($src['forecasts'])->pluck('target_date')->filter()->map(fn ($d) => (int) Carbon::parse($d)->year);

        return $service->create($user, (int) $entity->team_id, [
            'title' => 'Management Book ' . $entity->name,
            'description' => 'Übernommen aus dem Strategie-Layer der Organisation.',
            'status' => ManagementBookStatus::ACTIVE->value,
            'horizon_year' => $years->max(),
            'review_rhythm' => 'quarterly',
            'owner_user_id' => $user->id,
        ], (int) $entity->id);
    }

    private function bookForecastByOrgId(ManagementBook $book, int $orgForecastId): ?int
    {
        $uuid = DB::table('organization_forecasts')->where('id', $orgForecastId)->value('uuid');

        return $uuid ? ManagementBookForecast::withTrashed()->where('management_book_id', $book->id)->where('uuid', $uuid)->value('id') : null;
    }
}
