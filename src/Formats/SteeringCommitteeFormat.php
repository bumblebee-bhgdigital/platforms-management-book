<?php

namespace Platform\ManagementBook\Formats;

use Platform\ManagementBook\Enums\AgendaOutcomeCheck;
use Platform\ManagementBook\Enums\CommitteeType;

/**
 * Lenkungsausschuss: reguliert, was zwischen den Einheiten klemmt (BROICH.MANAGEMENTMODELL, „Die vier Fragen“,
 * „Hilfe statt Macht“). Erfolg = Handlungsfähigkeit: Ist die Organisation danach handlungsfähiger als vorher?
 */
class SteeringCommitteeFormat extends CommitteeFormat
{
    public function type(): CommitteeType
    {
        return CommitteeType::STEERING_COMMITTEE;
    }

    public function name(): string
    {
        return 'Regulation';
    }

    public function summary(): string
    {
        return 'Reguliert, was zwischen den Einheiten klemmt: Zielkonflikte, geteilte Ressourcen, Prioritäten über Bereichsgrenzen. Jeder Punkt folgt den vier Fragen.';
    }

    public function question(): string
    {
        return 'Ist die Organisation nach dieser Sitzung handlungsfähiger als vorher?';
    }

    public function agendaKinds(): array
    {
        return ['regulation' => 'Regulation', self::INFO => 'Information'];
    }

    public function decisionKinds(): array
    {
        return ['measure' => 'Maßnahme', 'return_to_unit' => 'Zurück an den Bereich', 'escalation' => 'Eskalation'];
    }

    public function agendaFields(): array
    {
        return [
            'goal' => ['no' => '1', 'label' => 'Was wollten wir erreichen?', 'placeholder' => 'z. B. Neues Bankettangebot bis Q2 am Markt'],
            'observation' => ['no' => '2', 'label' => 'Was beobachten wir tatsächlich?', 'placeholder' => 'z. B. Konzept steht, Kalkulation fehlt seit 6 Wochen'],
            'obstacle' => ['no' => '3', 'label' => 'Was hindert euch momentan?', 'placeholder' => 'z. B. Controlling ist mit dem Jahresabschluss ausgelastet'],
            'local_solution' => ['no' => '4a', 'label' => 'Was kann der Bereich selbst lösen?', 'placeholder' => 'z. B. Grobkalkulation selbst erstellen', 'aside' => true],
            'system_support' => ['no' => '4b', 'label' => 'Wobei braucht er das Gesamtsystem?', 'placeholder' => 'z. B. Priorisierung der Controlling-Kapazität', 'aside' => true],
        ];
    }

    public function outcomeChecks(): array
    {
        $checks = [];
        foreach (AgendaOutcomeCheck::cases() as $c) {
            $checks[$c->value] = $c->label();
        }

        return $checks;
    }

    public function outcome(): array
    {
        return [
            'stat' => 'Handlungsfähigkeit',
            'title' => 'Nach dem LA – was trifft zu?',
            'question' => 'Maßstab: Ist die Organisation danach handlungsfähiger als vorher?',
            'pending' => 'nach dem LA bewerten',
            'verdicts' => ['Handlungsfähiger als vorher', 'Teilweise handlungsfähiger', 'Eher berichtet als reguliert'],
        ];
    }

    public function texts(): array
    {
        return [
            'agenda_intro' => 'In den LA gehört, was zwischen den Einheiten klemmt. Jeder Punkt folgt den vier Fragen – vom Ziel über das Hindernis bis zur Frage, was der Bereich selbst lösen kann und wobei er das Gesamtsystem braucht.',
            'decision_intro' => 'Auch „zurück an den Bereich“ ist ein guter Beschluss – Regulation heißt nicht Zentralisierung. Jeder Beschluss braucht eine verantwortliche Person und eine Frist.',
            'empty_agenda' => '„Kapazität Dezember – drei Großaufträge treffen auf das Stadion-Geschäft. Der Bereich stellt Schichten um, braucht aber eine Priorisierung zwischen den Bereichen.“',
            'unguided' => 'ohne Leitfaden – aufklappen und die vier Fragen beantworten',
            'guide_title' => 'Leitfaden – die vier Fragen',
            'guide_button' => 'Leitfaden bearbeiten',
        ];
    }

    public function flow(?string $status): ?array
    {
        return match ($status) {
            'planned' => ['icon' => 'heroicon-o-pencil-square', 'title' => 'Vorbereiten', 'text' => 'Punkte mit Leitfaden füllen, Unterlagen anhängen, Teilnehmer einladen. Gute Vorbereitung heißt: Jeder Punkt beantwortet schon vorab, was der Bereich selbst lösen kann.'],
            'prepared' => ['icon' => 'heroicon-o-chat-bubble-left-right', 'title' => 'Durchführen', 'text' => 'Je Punkt die vier Fragen durchgehen, erst die Hindernisse verstehen, dann entscheiden. Auch „zurück an den Bereich“ ist ein gutes Ergebnis.'],
            'held' => ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Nachbereiten', 'text' => 'Kriterien abhaken, Protokoll freigeben, Beschlüsse verfolgen. Erst dann zeigt sich, ob die Organisation handlungsfähiger geworden ist.'],
            'cancelled' => ['icon' => 'heroicon-o-x-circle', 'title' => 'Abgesagt', 'text' => 'Offene Punkte gehen nicht verloren – übernehmt sie in die nächste Sitzung oder gebt sie bewusst an den Bereich zurück.'],
            default => null,
        };
    }

    public function stance(): ?array
    {
        return [
            'title' => 'Haltung im LA',
            'items' => ['Was hindert euch daran?', 'Was könnt ihr selbst verändern?', 'Was müssen wir als Gesamtsystem verändern, damit ihr wirksam handeln könnt?'],
            'note' => 'Erst Erkenntnis, dann eigene Verantwortung, dann die Rolle des Systems – nicht „Warum habt ihr das nicht erreicht?“.',
        ];
    }

    public function guideArea(): string
    {
        return 'meeting';
    }

    public function measuresCapability(): bool
    {
        return true;
    }

    public function itemHints(object $item): array
    {
        if (($item->kind ?? null) === self::INFO) {
            return [];
        }
        $hints = [];
        if (blank($item->local_solution ?? null)) {
            $hints[] = ['variant' => 'warning', 'icon' => 'heroicon-o-exclamation-triangle', 'text' => 'Ohne Antwort auf 4a droht Mikromanagement: Was bleibt beim Bereich?'];
        }
        if (blank($item->system_support ?? null)) {
            $hints[] = ['variant' => 'info', 'icon' => 'heroicon-o-information-circle', 'text' => 'Wenn das Gesamtsystem nichts beitragen muss – gehört der Punkt überhaupt in den LA?'];
        }

        return $hints;
    }
}
