<?php

namespace Platform\ManagementBook\Formats;

use Platform\ManagementBook\Enums\CommitteeType;

/**
 * Strategiemeeting: gibt die Richtung (Regelkreis Station 1). Standortbestimmung und Richtungsfragen statt
 * Regulation; Ergebnisse sind Richtungsentscheidungen, Ziele, Leitplanken und Fokusräume, die ins Book gehören.
 * Erfolg = Richtungsklarheit, nicht Handlungsfähigkeit.
 */
class StrategyMeetingFormat extends CommitteeFormat
{
    public function type(): CommitteeType
    {
        return CommitteeType::STRATEGY_MEETING;
    }

    public function name(): string
    {
        return 'Richtung';
    }

    public function summary(): string
    {
        return 'Gibt die Richtung: Standortbestimmung, Zielbild, Fokusräume, Ziele und Leitplanken. Was hier entschieden wird, gehört ins Management Book – den Alltag reguliert der Lenkungsausschuss.';
    }

    public function question(): string
    {
        return 'Weiß die Organisation danach, wohin sie will – und worauf sie verzichtet?';
    }

    public function agendaKinds(): array
    {
        return ['assessment' => 'Standortbestimmung', 'direction' => 'Richtungsfrage', self::INFO => 'Information'];
    }

    public function decisionKinds(): array
    {
        return ['direction' => 'Richtungsentscheidung', 'objective' => 'Ziel', 'guardrail' => 'Leitplanke', 'focus_area' => 'Fokusraum'];
    }

    public function agendaFields(): array
    {
        return [
            'observation' => ['no' => '1', 'label' => 'Ausgangslage – wo stehen wir?', 'placeholder' => 'z. B. Die Organisation steht, beim Kunden kommt zu wenig an'],
            'goal' => ['no' => '2', 'label' => 'Richtungsfrage – was müssen wir entscheiden?', 'placeholder' => 'z. B. Machen wir die Kundenbeziehung zum eigenen Fokusraum?'],
        ];
    }

    public function outcomeChecks(): array
    {
        return [
            'direction_clear' => 'Die Richtung ist klar',
            'priorities_set' => 'Prioritäten sind gesetzt',
            'trade_offs_named' => 'Verzicht ist benannt – was wir nicht tun',
            'book_updated' => 'Ins Management Book übertragen',
            'communication_planned' => 'Kommunikation in die Organisation ist geplant',
        ];
    }

    public function outcome(): array
    {
        return [
            'stat' => 'Richtungsklarheit',
            'title' => 'Nach dem Strategiemeeting – was trifft zu?',
            'question' => 'Maßstab: Weiß die Organisation danach, wohin sie will – und worauf sie verzichtet?',
            'pending' => 'nach dem Meeting bewerten',
            'verdicts' => ['Richtung geklärt', 'Teilweise geklärt', 'Eher diskutiert als entschieden'],
        ];
    }

    public function texts(): array
    {
        return [
            'agenda_intro' => 'Im Strategiemeeting geht es um Richtung, nicht um den Alltag: Wo stehen wir, was zeichnet sich ab, und welche Richtungsfragen entscheiden wir heute? Was zwischen den Einheiten klemmt, gehört in den Lenkungsausschuss.',
            'decision_intro' => 'Ergebnisse sind Richtungsentscheidungen, Ziele, Leitplanken und Fokusräume. Was entschieden ist, gehört ins Management Book – sonst bleibt es eine Folie.',
            'empty_agenda' => '„Kundenbeziehung – die Organisation steht, beim Kunden kommt zu wenig an. Machen wir die Kundenbeziehung zum eigenen Fokusraum?“',
            'unguided' => 'ohne Einordnung – aufklappen, Ausgangslage und Richtungsfrage festhalten',
            'guide_title' => 'Einordnung – Ausgangslage und Richtungsfrage',
            'guide_button' => 'Einordnung bearbeiten',
        ];
    }

    public function flow(?string $status): ?array
    {
        return match ($status) {
            'planned' => ['icon' => 'heroicon-o-pencil-square', 'title' => 'Vorbereiten', 'text' => 'Standort aufbereiten (Zielbild, Meilensteine, Zahlen) und die Richtungsfragen schärfen, die heute entschieden werden sollen. Eine Präsentation hilft, die Lage gemeinsam zu sehen.'],
            'prepared' => ['icon' => 'heroicon-o-chat-bubble-left-right', 'title' => 'Durchführen', 'text' => 'Erst die Lage gemeinsam verstehen, dann aus der Zukunft zurückdenken: Was soll beobachtbar sein – und was lassen wir dafür weg?'],
            'held' => ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Nachbereiten', 'text' => 'Richtungsentscheidungen ins Management Book übertragen (Fokusräume, Ziele, Leitplanken), Protokoll freigeben und die Kommunikation in die Organisation planen.'],
            'cancelled' => ['icon' => 'heroicon-o-x-circle', 'title' => 'Abgesagt', 'text' => 'Ohne Strategiemeeting fehlt dem Regelkreis die Richtung. Einen neuen Termin festlegen, bevor die Fokusplanung startet.'],
            default => null,
        };
    }

    public function stance(): ?array
    {
        return [
            'title' => 'Haltung im Strategiemeeting',
            'items' => ['Aus der Zukunft zurückdenken: Welcher Zustand soll beobachtbar sein?', 'Fokussieren heißt verzichten: Was lassen wir bewusst weg?', 'Was muss ins Book, damit es die Organisation trägt?'],
            'note' => 'Das Strategiemeeting gibt die Richtung. Den Alltag reguliert der Lenkungsausschuss – Umsetzungsfragen dorthin geben.',
        ];
    }

    public function guideArea(): string
    {
        return 'meeting_strategy';
    }
}
