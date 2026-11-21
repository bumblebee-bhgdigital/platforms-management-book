<?php

namespace Platform\ManagementBook\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\StrategicDocumentType;
use Platform\ManagementBook\Models\ManagementBookStrategicDocument;
use Platform\ManagementBook\Tools\Concerns\HandlesStrategy;

class UpdateBookStrategyTool implements ToolContract, ToolMetadataContract
{
    use HandlesStrategy;

    public function getName(): string
    {
        return 'management-book.strategy.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/books/{id}/strategy - Setzt Mission, Vision und/oder Kapitel eines Management Books. Pflicht: management_book_id und mindestens eines von '
            . implode('/', StrategicDocumentType::values()) . ' (Objekt mit content [Markdown], optional title, change_note). '
            . 'Kapitel: origin (Woher kommen wir?), current_situation (Wo stehen wir?), priorities, business_fields, principles (Führungs-/Entscheidungsgrundsätze), capabilities, collaboration. Versioniert: jede Änderung legt eine neue aktive Version an, die bisherige bleibt als Historie erhalten. '
            . 'Unveränderter Inhalt erzeugt keine neue Version.';
    }

    public function getSchema(): array
    {
        $doc = fn (string $what) => [
            'type' => 'object',
            'description' => "Optional: {$what} (neue aktive Version).",
            'properties' => [
                'content' => ['type' => 'string', 'description' => 'Markdown-Inhalt.'],
                'title' => ['type' => 'string', 'description' => 'Optional: Titel (Default: bisheriger Titel bzw. Mission/Vision).'],
                'change_note' => ['type' => 'string', 'description' => 'Optional: Grund der Änderung.'],
            ],
        ];

        return [
            'type' => 'object',
            'properties' => [
                'management_book_id' => ['type' => 'integer', 'description' => 'Management Book (ERFORDERLICH).'],
                'mission' => $doc('Mission – warum es das Book/den Knoten gibt (zeitlich stabil)'),
                'vision' => $doc('Vision – gewollter Zukunftszustand (5–10 Jahre)'),
            ] + collect(StrategicDocumentType::cases())
                ->reject(fn ($t) => in_array($t, [StrategicDocumentType::MISSION, StrategicDocumentType::VISION], true))
                ->mapWithKeys(fn ($t) => [$t->value => $doc("Kapitel „{$t->label()}“ – {$t->question()}")])
                ->all() + [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
            'required' => ['management_book_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            ['book' => $book, 'error' => $error] = $this->findBook($arguments, $team, $context, 'update');
            if ($error) {
                return $error;
            }

            $inputs = [];
            foreach (StrategicDocumentType::cases() as $type) {
                if (! array_key_exists($type->value, $arguments)) {
                    continue;
                }
                $in = $arguments[$type->value];
                if (! is_array($in) || (! array_key_exists('content', $in) && ! array_key_exists('title', $in))) {
                    return ToolResult::error('VALIDATION_ERROR', "{$type->value} muss ein Objekt mit content und/oder title sein.");
                }
                if (array_key_exists('title', $in) && trim((string) $in['title']) === '') {
                    return ToolResult::error('VALIDATION_ERROR', "{$type->value}.title darf nicht leer sein.");
                }
                $inputs[$type->value] = [$type, $in];
            }
            if ($inputs === []) {
                return ToolResult::error('VALIDATION_ERROR', 'Mindestens eines von ' . implode(', ', StrategicDocumentType::values()) . ' angeben.');
            }

            $changed = [];
            $unchanged = [];
            DB::transaction(function () use ($inputs, $book, $team, $context, &$changed, &$unchanged) {
                foreach ($inputs as $key => [$type, $in]) {
                    $current = ManagementBookStrategicDocument::activeFor($book->id, $type);
                    $content = array_key_exists('content', $in) ? (string) $in['content'] : $current?->content;
                    $title = array_key_exists('title', $in) ? trim((string) $in['title']) : ($current?->title ?? $type->label());

                    if ($current && $current->content === $content && $current->title === $title) {
                        $unchanged[] = $key;
                        continue;
                    }
                    ManagementBookStrategicDocument::create([
                        'team_id' => $team->id,
                        'management_book_id' => $book->id,
                        'type' => $type,
                        'title' => $title,
                        'content' => $content,
                        'is_active' => true,
                        'valid_from' => now()->toDateString(),
                        'change_note' => $in['change_note'] ?? null,
                        'created_by' => $context->user->id,
                    ]);
                    $changed[] = $key;
                }
            });

            $out = ['management_book_id' => $book->id];
            foreach (StrategicDocumentType::cases() as $type) {
                $doc = ManagementBookStrategicDocument::activeFor($book->id, $type);
                $out[$type->value] = $doc ? $this->documentToArray($doc) : null;
            }
            $out['changed'] = $changed;
            $out['unchanged'] = $unchanged;
            $out['message'] = $changed === []
                ? 'Keine Änderung (Inhalt identisch).'
                : 'Neue Version angelegt: ' . implode(', ', $changed) . '.';

            return ToolResult::success($out);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Speichern der Strategie: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('strategy', 'PUT');
    }
}
