<?php

namespace Platform\ManagementBook\Support;

use Illuminate\Support\Facades\Route;

/**
 * Orientierung je Bereich: worum es geht, warum es das gibt, ein Leitsatz aus dem BROICH.MANAGEMENTMODELL
 * und die passenden Academy-Lessons. Reine Erklärung, keine Logik.
 */
class Guide
{
    /** @return array{eyebrow:string,title:string,lead:string,why:string,principle:string,lessons:array<int,array{title:string,url:?string}>} */
    public static function for(string $area): array
    {
        $g = self::content()[$area] ?? self::content()['overview'];
        $g['lessons'] = array_values(array_filter(array_map(fn ($key) => self::lesson($key), $g['lessons'])));

        return $g;
    }

    /** @return array{title:string,url:?string}|null */
    public static function lesson(string $key): ?array
    {
        $l = config("management-book.academy.lessons.{$key}");
        if (! $l) {
            return null;
        }

        return [
            'title' => $l['title'],
            'url' => Route::has('academy.lessons.show') ? route('academy.lessons.show', $l['uuid']) : null,
        ];
    }

    private static function content(): array
    {
        return [
            'dashboard' => [
                'eyebrow' => 'Management Books',
                'title' => 'Orientierung, die nicht an einer Person hängt',
                'lead' => 'Ein Management Book verdichtet, wohin eine Einheit will und nach welcher Logik sie handelt. Es entsteht aus der Strategiearbeit und wird im Lenkungsausschuss laufend mit der Wirklichkeit abgeglichen.',
                'why' => 'Steckt die Orientierung nur in Köpfen, geht sie bei jedem Wechsel verloren. Das Book macht aus personengebundener Orientierung organisationsgebundene.',
                'principle' => 'Strategie wird nicht verkündet. Sie wird in der Organisation anschlussfähig gemacht.',
                'lessons' => ['regelkreis', 'roter_faden'],
            ],
            'overview' => [
                'eyebrow' => 'Übersicht',
                'title' => 'Wo steht dieses Book im Regelkreis?',
                'lead' => 'Strategiemeeting, Workshop, Book, Kick-off, Fokusplanung, Umsetzung, Lernen und Rückkopplung greifen ineinander. Die Übersicht zeigt, welche Stationen belegt sind und wo es gerade klemmt.',
                'why' => 'Ohne Rückkopplung wird Strategie zur Ideologie. Erst wenn Erkenntnisse aus der Umsetzung zurückfließen, bleibt das Book lebendig.',
                'principle' => 'Das Book erzeugt die Strategie nicht. Es verdichtet sie.',
                'lessons' => ['regelkreis', 'betriebssystem'],
            ],
            'book' => [
                'eyebrow' => 'Book',
                'title' => 'Sprache und Gedächtnis der Organisation',
                'lead' => 'Die Kapitel beantworten die Fragen, an denen sich alle orientieren: Woher kommen wir, wo stehen wir, wohin wollen wir, was hat Vorrang, nach welchen Grundsätzen entscheiden wir.',
                'why' => 'Das Book ist eine Momentaufnahme: stabil genug für Orientierung, veränderbar genug für Lernen. Jede Änderung wird als neue Version festgehalten.',
                'principle' => 'Ein Dokument trifft keine Entscheidungen. Es gibt ihnen eine gemeinsame Grundlage.',
                'lessons' => ['regelkreis', 'zukunftsbild', 'betriebssystem'],
            ],
            'strategy' => [
                'eyebrow' => 'Strategie',
                'title' => 'Aus der Zukunft zurückdenken',
                'lead' => 'Die Regnose beschreibt einen beobachtbaren Zielzustand. Fokusräume bündeln die wenigen Themen, die dorthin führen: je Raum Zielbilder, Hindernisse und Meilensteine als Waypoints über die Zeit.',
                'why' => 'Wer nur aus der Gegenwart fortschreibt, trägt die Gegenwart in die Zukunft. Fünf bis sieben Fokusräume zwingen zur Entscheidung, was wegfällt.',
                'principle' => 'Fokussieren heißt verzichten.',
                'lessons' => ['regnose', 'zukunftsbild', 'kick_off'],
            ],
            'risks' => [
                'eyebrow' => 'Risiken',
                'title' => 'Wissen, was uns treffen kann',
                'lead' => 'Jedes Risiko wird nach Eintrittswahrscheinlichkeit (E) und Auswirkung (A) bewertet. E × A ergibt die Priorität und die Ampel. Die Risikobereitschaft legt fest, wie viel Unsicherheit das Book bewusst trägt.',
                'why' => 'Eine Risikomatrix ersetzt kein Handeln, sie macht Prioritäten besprechbar. Was rot ist, gehört in den Lenkungsausschuss, was grün ist, wird beobachtet.',
                'principle' => 'Nicht die Ampel ist die Managementfrage, sondern warum das verantwortliche System das Risiko nicht selbst beherrscht.',
                'lessons' => ['vier_fragen', 'lenkungsausschuss'],
            ],
            'committees' => [
                'eyebrow' => 'Gremien',
                'title' => 'Wo Strategie und Wirklichkeit sich begegnen',
                'lead' => 'Das Strategiemeeting gibt die Richtung, der Lenkungsausschuss reguliert, was zwischen den Einheiten klemmt. Beides gehört zum selben Regelkreis.',
                'why' => 'Je stärker Verantwortung verteilt ist, desto sichtbarer werden Abhängigkeiten. Dafür braucht es einen Ort, der das Zusammenspiel des Ganzen im Blick hat.',
                'principle' => 'Der Lenkungsausschuss übernimmt keine Verantwortung. Er macht sie möglich.',
                'lessons' => ['lenkungsausschuss', 'hilfe_statt_macht'],
            ],
            'committee' => [
                'eyebrow' => 'Gremium',
                'title' => 'Lokal vor übergreifend',
                'lead' => 'Was innerhalb eines Verantwortungsbereichs gelöst werden kann, wird dort gelöst. Das Gremium beginnt dort, wo lokale Handlungsfähigkeit nicht mehr ausreicht: bei Zielkonflikten, geteilten Ressourcen und Prioritäten über Bereichsgrenzen.',
                'why' => 'Eskalation ist kein Versagen, sondern professionelle Führung. Wer die Grenze seines Verantwortungsraums erkennt und das Thema einbringt, verhindert, dass aus kleinen Problemen große werden.',
                'principle' => 'So viel Selbststeuerung wie möglich, so viel übergreifende Regulation wie notwendig.',
                'lessons' => ['lenkungsausschuss', 'vier_fragen'],
            ],
            'meeting' => [
                'eyebrow' => 'Sitzung',
                'title' => 'Vom Statusbericht zur Regulation',
                'lead' => 'Jeder Punkt folgt vier Fragen: Was wollten wir erreichen? Was beobachten wir? Was hindert uns? Was kann der Bereich selbst lösen, und wobei braucht er das Gesamtsystem?',
                'why' => 'Die vierte Frage schützt vor Mikromanagement und vor falsch verstandener Eigenverantwortung zugleich. Manchmal ist die beste Entscheidung, das Thema zurückzugeben.',
                'principle' => 'Ist die Organisation nach dieser Sitzung handlungsfähiger als vorher?',
                'lessons' => ['vier_fragen', 'hilfe_statt_macht'],
            ],
            'meeting_strategy' => [
                'eyebrow' => 'Strategiemeeting',
                'title' => 'Richtung geben, nicht regulieren',
                'lead' => 'Das Strategiemeeting beantwortet die Grundsatzfragen: Wo stehen wir, was zeichnet sich ab, wohin wollen wir? Ergebnisse sind Richtungsentscheidungen, Ziele, Leitplanken und Fokusräume.',
                'why' => 'Was hier entschieden wird, trägt erst, wenn es im Book steht und in der Organisation ankommt. Umsetzungsfragen und Konflikte zwischen den Einheiten gehören in den Lenkungsausschuss.',
                'principle' => 'Fokussieren heißt verzichten.',
                'lessons' => ['regelkreis', 'regnose', 'zukunftsbild'],
            ],
            'meeting_board' => [
                'eyebrow' => 'Board',
                'title' => 'Kontrollieren und freigeben',
                'lead' => 'Das Board versteht die Lage, prüft Abweichungen und Risiken und entscheidet über Vorlagen: Freigabe, Auflage oder Ablehnung.',
                'why' => 'Ein Board, das sich in die Umsetzung einmischt, schwächt die Verantwortung der Geschäftsführung. Eine klare Auflage mit Frist wirkt mehr als eine lange Diskussion.',
                'principle' => 'Das Board kontrolliert und gibt frei. Umgesetzt wird in der Organisation.',
                'lessons' => ['regelkreis', 'betriebssystem'],
            ],
            'meeting_generic' => [
                'eyebrow' => 'Sitzung',
                'title' => 'Themen klären, Ergebnisse festhalten',
                'lead' => 'Je Thema Ausgangslage und Ziel festhalten, Ergebnisse als Beschlüsse mit Verantwortlichen und Frist dokumentieren.',
                'why' => 'Auch ohne feste Methodik gilt: Ein Beschluss ohne Person und Frist ist eine Absicht.',
                'principle' => 'Ein Dokument trifft keine Entscheidungen. Es gibt ihnen eine gemeinsame Grundlage.',
                'lessons' => ['regelkreis'],
            ],
        ];
    }
}
