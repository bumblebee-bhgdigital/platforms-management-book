<?php

namespace Platform\ManagementBook\Formats;

use Platform\ManagementBook\Enums\CommitteeType;

/** Sonstige Gremien: schlichtes Format ohne Methodik (Thema, Ausgangslage, Ziel, Ergebnis). */
class GenericFormat extends CommitteeFormat
{
    public function type(): CommitteeType
    {
        return CommitteeType::OTHER;
    }

    public function name(): string
    {
        return 'Besprechung';
    }

    public function summary(): string
    {
        return 'Schlichtes Format ohne feste Methodik: Themen mit Ausgangslage und Ziel, Ergebnisse als Beschlüsse oder Aufgaben.';
    }

    public function question(): string
    {
        return 'Sind die Themen geklärt und die nächsten Schritte klar?';
    }

    public function agendaKinds(): array
    {
        return ['topic' => 'Thema', self::INFO => 'Information'];
    }

    public function decisionKinds(): array
    {
        return ['decision' => 'Beschluss', 'task' => 'Aufgabe'];
    }

    public function agendaFields(): array
    {
        return [
            'observation' => ['no' => '1', 'label' => 'Ausgangslage', 'placeholder' => 'Worum geht es, was ist der Stand?'],
            'goal' => ['no' => '2', 'label' => 'Ziel des Punkts', 'placeholder' => 'Was soll am Ende geklärt sein?'],
        ];
    }

    public function outcomeChecks(): array
    {
        return [
            'topic_clarified' => 'Das Thema ist geklärt',
            'next_steps_clear' => 'Nächste Schritte sind klar',
            'responsibilities_clear' => 'Verantwortlichkeiten sind eindeutig',
        ];
    }

    public function outcome(): array
    {
        return [
            'stat' => 'Ergebnis',
            'title' => 'Nach der Sitzung – was trifft zu?',
            'question' => 'Maßstab: Sind die Themen geklärt und die nächsten Schritte klar?',
            'pending' => 'nach der Sitzung bewerten',
            'verdicts' => ['Geklärt', 'Teilweise geklärt', 'Noch offen'],
        ];
    }

    public function texts(): array
    {
        return [
            'agenda_intro' => 'Themen der Sitzung mit Ausgangslage und Ziel. Ergebnisse werden als Beschlüsse oder Aufgaben festgehalten.',
            'decision_intro' => 'Jeder Beschluss braucht eine verantwortliche Person und eine Frist.',
            'empty_agenda' => '„Jahresplanung – Stand der Vorbereitung, Ziel: Termine und Verantwortliche festlegen.“',
            'unguided' => 'ohne Einordnung – aufklappen und Ausgangslage festhalten',
            'guide_title' => 'Einordnung',
            'guide_button' => 'Einordnung bearbeiten',
        ];
    }

    public function flow(?string $status): ?array
    {
        return match ($status) {
            'planned' => ['icon' => 'heroicon-o-pencil-square', 'title' => 'Vorbereiten', 'text' => 'Themen sammeln, Unterlagen anhängen, Teilnehmer einladen.'],
            'prepared' => ['icon' => 'heroicon-o-chat-bubble-left-right', 'title' => 'Durchführen', 'text' => 'Je Thema Ausgangslage klären und Ergebnisse festhalten.'],
            'held' => ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Nachbereiten', 'text' => 'Protokoll freigeben und Beschlüsse verfolgen.'],
            'cancelled' => ['icon' => 'heroicon-o-x-circle', 'title' => 'Abgesagt', 'text' => 'Offene Themen in die nächste Sitzung übernehmen.'],
            default => null,
        };
    }

    public function stance(): ?array
    {
        return null;
    }

    public function guideArea(): string
    {
        return 'meeting_generic';
    }
}
