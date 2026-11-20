<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Enums\StrategicDocumentType;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Mission oder Vision eines Management Books (versioniert; pro Book und Typ ist genau eine Version aktiv). Mission = warum es das gibt (stabil), Vision = gewollter Zukunftszustand.
 *
 * @property-read string $uuid
 */
class ManagementBookStrategicDocument extends Model implements HasDisplayName
{
    use SoftDeletes, GeneratesUuid;

    protected $table = 'management_book_strategic_documents';

    protected $fillable = [
        'uuid',
        'team_id',
        'management_book_id',
        'type',
        'title',
        'content',
        'version',
        'is_active',
        'valid_from',
        'change_note',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'type' => StrategicDocumentType::class,
        'version' => 'integer',
        'is_active' => 'boolean',
        'valid_from' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if ($model->is_active) {
                self::query()->where('management_book_id', $model->management_book_id)
                    ->where('type', $model->type instanceof StrategicDocumentType ? $model->type->value : $model->type)
                    ->update(['is_active' => false]);
            }
            if (empty($model->version)) {
                $model->version = 1 + (int) self::withTrashed()->where('management_book_id', $model->management_book_id)
                    ->where('type', $model->type instanceof StrategicDocumentType ? $model->type->value : $model->type)
                    ->max('version');
            }
            if (empty($model->valid_from)) {
                $model->valid_from = now()->toDateString();
            }
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(ManagementBook::class, 'management_book_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, StrategicDocumentType|string $type): Builder
    {
        return $query->where('type', $type instanceof StrategicDocumentType ? $type->value : $type);
    }

    /** Aktive Version eines Typs für ein Book (oder null). */
    public static function activeFor(int $bookId, StrategicDocumentType $type): ?self
    {
        return self::query()->where('management_book_id', $bookId)->ofType($type)->active()
            ->orderByDesc('version')->first();
    }

    /** Neue aktive Version; die bisherige wird inaktiv. */
    public function createNewVersion(array $attributes = []): self
    {
        return DB::transaction(function () use ($attributes) {
            return self::create(array_merge([
                'team_id' => $this->team_id,
                'management_book_id' => $this->management_book_id,
                'type' => $this->type,
                'title' => $this->title,
                'content' => $this->content,
                'is_active' => true,
                'valid_from' => now()->toDateString(),
            ], $attributes));
        });
    }
}
