<?php

namespace Platform\ManagementBook\Formats;

use Platform\ManagementBook\Enums\CommitteeType;

/**
 * Format eines Gremientyps: Was ein Agenda-Punkt enthält, welche Arten von Punkten und Beschlüssen es gibt,
 * woran der Erfolg einer Sitzung gemessen wird und welche Leitfäden die Seite zeigt.
 *
 * Alle Gremien teilen dasselbe Datenmodell (Gremium, Sitzung, Agenda, Beschluss, Protokoll). Das Format legt
 * nur Verhalten und Sprache fest; die Agenda-Felder sind Spalten von management_book_agenda_items
 * (goal, observation, obstacle, local_solution, system_support), die je Format anders benannt werden.
 * Neue Gremientypen = neue Klasse, keine neuen Tabellen.
 */
abstract class CommitteeFormat
{
    /** Spalten am Agenda-Punkt, die ein Format als Leitfaden-Felder nutzen kann. */
    public const AGENDA_COLUMNS = ['goal', 'observation', 'obstacle', 'local_solution', 'system_support'];

    /** Agenda-Art ohne Leitfaden und ohne Erfolgsmessung (in allen Formaten). */
    public const INFO = 'info';

    abstract public function type(): CommitteeType;

    /** Kurzname des Formats, z. B. „Regulation“. */
    abstract public function name(): string;

    /** Ein bis zwei Sätze: wozu dieses Gremium tagt. */
    abstract public function summary(): string;

    /** Leitfrage des Formats. */
    abstract public function question(): string;

    /** @return array<string,string> Agenda-Arten (Wert => Label), erste = Standard */
    abstract public function agendaKinds(): array;

    /** @return array<string,string> Beschluss-Arten (Wert => Label), erste = Standard */
    abstract public function decisionKinds(): array;

    /**
     * Leitfaden-Felder je Agenda-Punkt in Anzeigereihenfolge.
     *
     * @return array<string, array{no:string, label:string, placeholder:string, aside?:bool}> Spalte => Beschreibung
     */
    abstract public function agendaFields(): array;

    /** @return array<string,string> Erfolgskriterien je Punkt (Wert => Label) */
    abstract public function outcomeChecks(): array;

    /**
     * Texte der Erfolgsmessung.
     *
     * @return array{stat:string, title:string, question:string, pending:string, verdicts:array{0:string,1:string,2:string}}
     */
    abstract public function outcome(): array;

    /**
     * Seitentexte.
     *
     * @return array{agenda_intro:string, decision_intro:string, empty_agenda:string, unguided:string, guide_title:string, guide_button:string}
     */
    abstract public function texts(): array;

    /** @return array{icon:string,title:string,text:string}|null Ablauf-Hinweis je Sitzungsstatus */
    abstract public function flow(?string $status): ?array;

    /** @return array{title:string, items:string[], note:string}|null Haltung/Rolle in der Seitenspalte */
    abstract public function stance(): ?array;

    /** Bereich in Support\Guide für die Orientierungsspalte der Sitzung. */
    abstract public function guideArea(): string;

    /** Zählt die Sitzung als „Handlungsfähigkeit“ (Kennzahl der Organisation)? Nur der Lenkungsausschuss. */
    public function measuresCapability(): bool
    {
        return false;
    }

    /**
     * Hinweise zu einem Agenda-Punkt (z. B. fehlende Antwort auf 4a im LA).
     *
     * @return array<int, array{variant:string, icon:string, text:string}>
     */
    public function itemHints(object $item): array
    {
        return [];
    }

    // ── abgeleitet ───────────────────────────────────────────

    public function key(): string
    {
        return $this->type()->value;
    }

    public function defaultAgendaKind(): string
    {
        return (string) array_key_first($this->agendaKinds());
    }

    public function defaultDecisionKind(): string
    {
        return (string) array_key_first($this->decisionKinds());
    }

    public function agendaKindLabel(?string $kind): string
    {
        return $this->agendaKinds()[$kind ?: $this->defaultAgendaKind()] ?? (string) $kind;
    }

    public function decisionKindLabel(?string $kind): string
    {
        return $this->decisionKinds()[$kind ?: $this->defaultDecisionKind()] ?? (string) $kind;
    }

    /** Fehlermeldung oder null. Leerer Wert = Standard-Art. */
    public function validateAgendaKind(?string $kind): ?string
    {
        if ($kind === null || $kind === '' || isset($this->agendaKinds()[$kind])) {
            return null;
        }

        return "kind „{$kind}“ passt nicht zum Format {$this->type()->label()}. Erlaubt: " . implode(', ', array_keys($this->agendaKinds())) . '.';
    }

    public function validateDecisionKind(?string $kind): ?string
    {
        if ($kind === null || $kind === '' || isset($this->decisionKinds()[$kind])) {
            return null;
        }

        return "kind „{$kind}“ passt nicht zum Format {$this->type()->label()}. Erlaubt: " . implode(', ', array_keys($this->decisionKinds())) . '.';
    }

    /** @param string[] $checks */
    public function validateChecks(array $checks): ?string
    {
        $unknown = array_diff($checks, array_keys($this->outcomeChecks()));

        return $unknown === [] ? null
            : 'Unbekannte outcome_checks für ' . $this->type()->label() . ': ' . implode(', ', $unknown) . '. Erlaubt: ' . implode(', ', array_keys($this->outcomeChecks())) . '.';
    }

    /** Hat der Punkt Leitfaden-Inhalt oder Kriterien? Informationspunkte zählen nie. */
    public function isGuided(object $item): bool
    {
        if (($item->kind ?? null) === self::INFO) {
            return false;
        }
        foreach (array_keys($this->agendaFields()) as $field) {
            if (filled($item->{$field} ?? null)) {
                return true;
            }
        }

        return ! empty($item->outcome_checks);
    }

    /** Nur Kriterien, die zum Format gehören (Altwerte anderer Formate zählen nicht). */
    public function checksOf(object $item): array
    {
        return array_values(array_intersect($item->outcome_checks ?? [], array_keys($this->outcomeChecks())));
    }

    /**
     * Erfolg einer Sitzung über ihre geführten Punkte.
     *
     * @param iterable<object> $items
     * @return array{done:int, max:int, guided:int, pct:?int}
     */
    public function assess(iterable $items): array
    {
        $guided = 0;
        $done = 0;
        foreach ($items as $item) {
            if ($this->isGuided($item)) {
                $guided++;
                $done += count($this->checksOf($item));
            }
        }
        $max = $guided * count($this->outcomeChecks());

        return ['done' => $done, 'max' => $max, 'guided' => $guided, 'pct' => $max > 0 ? (int) round($done / $max * 100) : null];
    }

    /** @return array{variant:string,label:string} Einordnung nach Anteil erfüllter Kriterien */
    public function verdict(int $done, int $max): array
    {
        [$good, $partial, $weak] = $this->outcome()['verdicts'];
        $ratio = $max > 0 ? $done / $max : 0;

        return match (true) {
            $ratio >= 0.8 => ['variant' => 'success', 'label' => $good],
            $ratio >= 0.5 => ['variant' => 'warning', 'label' => $partial],
            default => ['variant' => 'danger', 'label' => $weak],
        };
    }

    /** Beschreibung für Agenten (MCP): Felder, Arten, Kriterien. */
    public function toArray(): array
    {
        $outcome = $this->outcome();

        return [
            'key' => $this->key(),
            'name' => $this->name(),
            'summary' => $this->summary(),
            'question' => $this->question(),
            'agenda_kinds' => $this->agendaKinds(),
            'default_agenda_kind' => $this->defaultAgendaKind(),
            'agenda_fields' => array_map(fn ($f) => $f['label'], $this->agendaFields()),
            'decision_kinds' => $this->decisionKinds(),
            'default_decision_kind' => $this->defaultDecisionKind(),
            'outcome' => ['label' => $outcome['stat'], 'question' => $outcome['question'], 'checks' => $this->outcomeChecks()],
            'measures_capability' => $this->measuresCapability(),
            'hint' => 'Agenda-Punkte vom Typ info haben keinen Leitfaden und zählen nicht in die Erfolgsmessung. '
                . 'Nicht genannte Leitfaden-Spalten gehören nicht zu diesem Format und bleiben leer.',
        ];
    }

    // ── Registry ─────────────────────────────────────────────

    public static function for(?CommitteeType $type): self
    {
        return match ($type) {
            CommitteeType::STEERING_COMMITTEE => new SteeringCommitteeFormat(),
            CommitteeType::STRATEGY_MEETING => new StrategyMeetingFormat(),
            CommitteeType::BOARD => new BoardFormat(),
            default => new GenericFormat(),
        };
    }

    /** @return self[] */
    public static function all(): array
    {
        return array_map(fn (CommitteeType $t) => self::for($t), CommitteeType::cases());
    }

    /** Label einer Art über alle Formate (für Listen ohne Gremium-Kontext). */
    public static function anyAgendaKindLabel(?string $kind): ?string
    {
        foreach (self::all() as $f) {
            if ($kind && isset($f->agendaKinds()[$kind])) {
                return $f->agendaKinds()[$kind];
            }
        }

        return null;
    }

    public static function anyDecisionKindLabel(?string $kind): ?string
    {
        foreach (self::all() as $f) {
            if ($kind && isset($f->decisionKinds()[$kind])) {
                return $f->decisionKinds()[$kind];
            }
        }

        return null;
    }
}
