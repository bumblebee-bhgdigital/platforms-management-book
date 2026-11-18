<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Models\Concerns\HasEntityLinks;
use Platform\ManagementBook\Enums\ManagementBookStatus;
use Platform\ManagementBook\Enums\ReviewRhythm;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Management Book = Steuerungsdokument eines Knotens (Organisationseinheit): verdichtet Strategie, Risiken, Analysen und Gremien. Pro Knoten ist genau ein Book "aktiv"; weitere Books (Entwurf, abgelöst, archiviert) können parallel existieren.
 *
 * Morph-Alias: `management_book` (Relation::morphMap im ServiceProvider). Der Knoten hängt NICHT als
 * Fremdschluessel am Book, sondern lose per DimensionLink (Entity-Dimension der Organization) —
 * wie PlannerProject. Verknüpfung/Lösen/Umhängen laufen über ManagementBookService.
 *
 * @property-read string $uuid
 */
class ManagementBook extends Model implements HasDisplayName
{
    use SoftDeletes, HasEntityLinks;

    /** Morph-Alias für DimensionLinks / EntityLinkProvider. */
    public const MORPH_ALIAS = 'management_book';

    protected $table = 'management_books';

    protected $fillable = [
        'uuid',
        'team_id',
        'title',
        'description',
        'status',
        'activated_at',
        'valid_from',
        'valid_until',
        'horizon_year',
        'owner_user_id',
        'responsible_agent_user_id',
        'review_rhythm',
        'next_review_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'status' => ManagementBookStatus::class,
        'review_rhythm' => ReviewRhythm::class,
        'activated_at' => 'datetime',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'next_review_at' => 'date',
        'horizon_year' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::withTrashed()->where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
            if (empty($model->status)) {
                $model->status = ManagementBookStatus::DRAFT;
            }
        });
    }

    // ── Beziehungen ──────────────────────────────────────────────

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owner_user_id');
    }

    public function responsibleAgent(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'responsible_agent_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    /**
     * KASKADE (Platzhalter): Management Book -> Gremien -> Sitzungen -> Agenda/Unterlagen/Protokoll/Beschlüsse.
     *
     * Gremien sind eigene Entitäten in einem Folge-Board und hängen per FK `management_book_id`
     * (Konvention) an diesem Book. Das Gremium-Model existiert noch nicht; sobald es da ist, wird
     * seine Klasse unter `management-book.cascade.committee_model` konfiguriert (oder dieser
     * Platzhalter ersetzt). Bis dahin liefert die Relation eine garantiert leere Menge, damit
     * Aufrufer (Detailseite, Tools) schon heute `$book->committees` nutzen können.
     */
    public function committees(): HasMany
    {
        $class = config('management-book.cascade.committee_model');

        if (is_string($class) && class_exists($class)) {
            return $this->hasMany($class, 'management_book_id');
        }

        // Null-Object: gleiche Relation, immer leer. Kein Query gegen eine nicht existierende Tabelle.
        return $this->hasMany(static::class, 'id', 'id')->whereRaw('1 = 0');
    }

    /**
     * Relationspfade der Kinder (Zeit-Kaskade, vgl. HasChildContextRelations im Planner).
     * Noch keine Kinder – Gremien/Sitzungen ergänzen 'committees', 'committees.meetings'.
     *
     * @return string[]
     */
    public static function childContextRelations(): array
    {
        return [];
    }

    // ── Anzeige ──────────────────────────────────────────────────

    public function getDisplayName(): ?string
    {
        return $this->title;
    }

    // ── Scopes / Sichtbarkeit ────────────────────────────────────

    public function scopeStatus(Builder $query, ManagementBookStatus|string $status): Builder
    {
        return $query->where($query->getModel()->getTable() . '.status', $status instanceof ManagementBookStatus ? $status->value : $status);
    }

    /**
     * Sichtbar = Core-Graph (Ersteller ODER über den Org-Baum erreichbar, read) ODER Owner des Books.
     * Spiegelt ManagementBookPolicy::view().
     */
    public function scopeVisibleTo(Builder $query, \Platform\Core\Models\User $user): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($user, $table) {
            $q->authzVisibleTo($user, 'read')
                ->orWhere($table . '.owner_user_id', $user->id);
        });
    }

    /** Alias wie im Planner (Sidebar). */
    public function scopeViewableBy(Builder $query, \Platform\Core\Models\User $user): Builder
    {
        return $this->scopeVisibleTo($query, $user);
    }

    public function isActive(): bool
    {
        return $this->status === ManagementBookStatus::ACTIVE;
    }
}
