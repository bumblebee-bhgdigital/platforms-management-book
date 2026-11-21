<?php

namespace Platform\ManagementBook\Formats;

use Platform\ManagementBook\Enums\CommitteeType;

/**
 * Board: Bericht und Kontrolle. Lage verstehen (Zahlen, Risiken, Abweichungen), über Vorlagen entscheiden,
 * Auflagen mit Verantwortlichen und Frist mitgeben. Erfolg = Entscheidungsklarheit.
 */
class BoardFormat extends CommitteeFormat
{
    public function type(): CommitteeType
    {
        return CommitteeType::BOARD;
    }

    public function name(): string
    {
        return 'Kontrolle & Freigabe';
    }

    public function summary(): string
    {
        return 'Bericht und Kontrolle: Lage, Zahlen und Risiken verstehen, über Vorlagen entscheiden, Auflagen mit Frist mitgeben. Die Umsetzung bleibt bei Geschäftsführung und Lenkungsausschuss.';
    }

    public function question(): string
    {
        return 'Ist nach dieser Sitzung klar, was freigegeben ist und unter welchen Auflagen?';
    }

    public function agendaKinds(): array
    {
        return ['report' => 'Bericht', 'approval' => 'Vorlage zur Freigabe', self::INFO => 'Information'];
    }

    public function decisionKinds(): array
    {
        return ['approval' => 'Freigabe', 'condition' => 'Auflage', 'rejection' => 'Ablehnung'];
    }

    public function agendaFields(): array
    {
        return [
            'observation' => ['no' => '1', 'label' => 'Bericht / Lage – was ist der Stand?', 'placeholder' => 'z. B. Umsatz Q3 liegt 8 % unter Plan, Kosten im Rahmen'],
            'goal' => ['no' => '2', 'label' => 'Antrag / Vorlage – worüber soll das Board befinden?', 'placeholder' => 'z. B. Freigabe Budget 2027 und Szenarioplanung'],
            'obstacle' => ['no' => '3', 'label' => 'Risiko / Abweichung – was weicht ab, was droht?', 'placeholder' => 'z. B. Abhängigkeit von einem Kunden, Lücke zum Umsatzziel'],
        ];
    }

    public function outcomeChecks(): array
    {
        return [
            'situation_understood' => 'Die Lage ist verstanden',
            'risks_assessed' => 'Risiken sind bewertet',
            'approvals_given' => 'Vorlagen sind entschieden',
            'conditions_with_deadline' => 'Auflagen haben Verantwortliche und Frist',
            'followup_clear' => 'Was das Board als Nächstes sehen will, ist klar',
        ];
    }

    public function outcome(): array
    {
        return [
            'stat' => 'Entscheidungsklarheit',
            'title' => 'Nach dem Board – was trifft zu?',
            'question' => 'Maßstab: Ist klar, was freigegeben ist und unter welchen Auflagen?',
            'pending' => 'nach dem Board bewerten',
            'verdicts' => ['Klar entschieden', 'Teilweise entschieden', 'Eher berichtet als entschieden'],
        ];
    }

    public function texts(): array
    {
        return [
            'agenda_intro' => 'Ins Board gehören Bericht, Kontrolle und Freigaben: Wie ist die Lage, was weicht ab, und worüber entscheidet das Board heute? Operative Regulation gehört in den Lenkungsausschuss.',
            'decision_intro' => 'Ergebnisse sind Freigaben, Auflagen und Ablehnungen. Jede Auflage braucht eine verantwortliche Person und eine Frist.',
            'empty_agenda' => '„Budget 2027 – Bericht zur Planung mit Worst, Base und Best Case, Vorlage zur Freigabe des Base Case.“',
            'unguided' => 'ohne Einordnung – aufklappen, Lage und Vorlage festhalten',
            'guide_title' => 'Einordnung – Lage, Vorlage, Abweichung',
            'guide_button' => 'Einordnung bearbeiten',
        ];
    }

    public function flow(?string $status): ?array
    {
        return match ($status) {
            'planned' => ['icon' => 'heroicon-o-pencil-square', 'title' => 'Vorbereiten', 'text' => 'Bericht und Vorlagen rechtzeitig verteilen: Zahlen, Risiken, Abweichungen und klar formulierte Anträge. Das Board entscheidet besser, wenn es vorher lesen konnte.'],
            'prepared' => ['icon' => 'heroicon-o-chat-bubble-left-right', 'title' => 'Durchführen', 'text' => 'Erst die Lage verstehen, dann je Vorlage entscheiden: Freigabe, Auflage oder Ablehnung – jeweils mit Verantwortlichen und Frist.'],
            'held' => ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Nachbereiten', 'text' => 'Freigaben und Auflagen festhalten, Protokoll freigeben und die Auflagen bis zur nächsten Sitzung nachhalten.'],
            'cancelled' => ['icon' => 'heroicon-o-x-circle', 'title' => 'Abgesagt', 'text' => 'Offene Vorlagen gehen nicht verloren – in die nächste Sitzung übernehmen oder per Umlauf klären.'],
            default => null,
        };
    }

    public function stance(): ?array
    {
        return [
            'title' => 'Rolle des Boards',
            'items' => ['Verstehen wir die Lage – Zahlen, Risiken, Abweichungen?', 'Worüber entscheiden wir heute?', 'Welche Auflagen geben wir mit – wer, bis wann?'],
            'note' => 'Das Board kontrolliert und gibt frei. Die Umsetzung bleibt bei der Geschäftsführung und im Lenkungsausschuss.',
        ];
    }

    public function guideArea(): string
    {
        return 'meeting_board';
    }
}
