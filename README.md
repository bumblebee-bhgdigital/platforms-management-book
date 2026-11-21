# platforms-management-book

Plattform-Modul: Management Book (Strategie, Analysen, Risiken, Gremien/Sitzungen).
Gerüst aus `martin3r-me/platform-module-template`; noch keine Fachlogik. Namespace `Platform\ManagementBook`, Paket `martin3r/platform-management-book`. Doku zum Gerüst: `LLM_GUIDE.md`, `DESIGN.md`.

## Strategie im Book (#1053)

Je Management Book: Mission/Vision (versioniert), Forecasts (+Versionen/Regnose, Zieljahr), Fokusräume (empfohlen 5–7) mit den drei Spalten Zielbilder / Hindernisse / Meilensteine (je Leitfrage). Felder und Verhalten orientieren sich 1:1 an den `organization.*`-Strategie-Tools (book-FK statt Entity). Keine Datenmigration.

MCP-Tools (Präfix `management-book.`): `strategy.GET` (aggregierte Sicht, analog `organization.strategy.GET`), `strategy.PUT` (Mission/Vision), `forecasts.GET/POST/PUT/DELETE`, `forecasts.versions.POST`, `focus_areas.*`, `vision_images.*`, `obstacles.*`, `milestones.*` (je GET/POST/PUT/DELETE). Rechte werden vom Management Book geerbt.

### Transformation Map und Milestone-Contributions (#1053, Teil 2)

Meilensteine sind Waypoints der Transformation Map: Zeitachse `target_year`/`target_quarter` (+ optional `target_date`), Reihenfolge `order`, Status `planned|in_progress|reached|missed|cancelled` (`reached` setzt `reached_at`). Tools: `transformation_map.GET` (Waypoints über die Zeit, Fokusraum-Bahnen, Kennzahlen, überfällige/nächster Waypoint), `transformation_map.PUT` (Status/Reihenfolge/Zeitpunkt mehrerer Waypoints, alles oder nichts).

Objectives und Key Results aus dem OKR-Modul zahlen über `milestone_contributions.GET/POST/DELETE` auf Meilensteine des Books ein (`linkable_type` = Morph-Alias, z. B. `okr_objective`, `okr_key_result`; lose gekoppelt wie `organization_milestone_contributions`, kein Eingriff ins OKR-Modul oder in Org-Tabellen).

## Kinder einer Sitzung (#1052)

Je Sitzung: Agenda-Punkte, Unterlagen, Protokoll, Beschlüsse, Teilnehmer. Reine Datenhaltung ("dumm aber vollständig"): keine Standard-Agenda, keine Ablaufregeln, keine Automatik (kein Auto-Übernehmen der Gremiumsmitglieder, keine Freigabe-Automatik, keine automatische Aufgabenanlage). Alle Felder sind frei schreibbar (auch `approved_at`, `approved_by`, `attended`, `status`), damit historische Sitzungen vollständig abbildbar sind. Rechte werden von der Sitzung (und damit Gremium/Book) geerbt; `team_id` wird aus der Sitzung abgeleitet. Keine Datenmigration.

MCP-Tools (Präfix `management-book.`, je GET/POST/PUT/DELETE; Anlegen immer mit `meeting_id`):

- `agenda_items.*`: `title`, `position`, `description`, `owner_user_id|owner_agent_id`, `duration_minutes`, optional lose `linked_object_type`+`linked_object_id` (Morph-Alias).
- `documents.*`: `title`, `type` (frei), `agenda_item_id`, `context_file_ids`. Dateien liegen in den Core-ContextFiles (Verknüpfung über `context_file_references` via `HasContextFileReferences`, kein eigener Speicher); es werden nur Dateien des eigenen Teams verknüpft. PUT: `context_file_ids` fügt hinzu, `remove_context_file_ids` entfernt die Verknüpfung (Datei bleibt). GET liefert `files`. Hochladen der Datei selbst weiterhin über den Core-Upload.
- `minutes.*`: `content` (Markdown), `approved_at`, `approved_by`. Mehrere Protokolle je Sitzung möglich (keine Eindeutigkeit erzwungen).
- `decisions.*`: `text`, `status` (`open|implemented|discarded`, Default `open`), `agenda_item_id`, `owner_user_id|owner_agent_id`, `due_date`, `linked_task_id` (lose Planner-Task-ID ohne FK).
- `meeting_attendees.*`: genau eines von `user_id|agent_id`, `role` (frei), `attended` (Default false). Je Sitzung ist ein User/Agent nur einmal erlaubt. Löschen ist hart, alle anderen Kinder werden soft-gelöscht.

Soft-Delete einer Sitzung kaskadiert nicht auf die Kinder (wie Gremium -> Sitzung); die Kinder sind dann über die Policy nicht mehr les-/schreibbar. Tests: `tests/Feature/MeetingChildrenSmokeTest.php` (läuft in der Verifikations-App `/app` mit sqlite-`:memory:`; Anleitung im Datei-Kopf).

## Risiken im Book (#1054, Spezifikation #1048)

Je Management Book eine Risikomatrix nach der Vorlage von Burkhard Schmitz. Reine Datenhaltung, alle Felder frei schreibbar (auch `status`), keine Ablauflogik.

- **Risiko** (`management_book_risks`): `number` (Risikonr.), `title`, `description`, `organization_entity_id` (Bereich/Abteilung als Knoten der Organisation, lose ohne FK), `probability` E (1–5), `impact` A (1–5), `score` = E × A (berechnet, gespeichert), `category` (frei), `measures_planned`, `measures_implemented`, `owner_user_id|owner_agent_id`, `due_date` (Frist), `status` (`open|monitored|treated|closed`, Default `open`), `review_date`. Soft-Delete.
- **Ampel** (abgeleitet, nicht gespeichert): 1–4 `low` (grün, beobachten) · 5–9 `moderate` (gelb, Maßnahmen prüfen) · 10–15 `high` (orange, umsetzen + überwachen) · 16–25 `critical` (rot, sofort handeln). Ohne E oder A kein Score.
- **Risikobereitschaft** am Book: `risk_appetite` (`very_low|low|moderate|high|very_high`, Risiko-Skala Q-Meeting Folie 10), über `management-book.books.PUT`.

MCP-Tools (Präfix `management-book.`): `risks.GET` (Filter `status`, `level`, `organization_entity_id`, `category`, `owner_user_id`, `search`; nach Score absteigend; mit `management_book_id` zusätzlich 5×5-Matrix, Verteilung je Ampelstufe und Risikobereitschaft), `risks.POST`, `risks.PUT`, `risks.DELETE`. Rechte erbt das Risiko vom Book. Tests: `tests/Feature/RisksSmokeTest.php` (Pfad zum Modul per `MB_MODULE_PATH`).

## MVP-UI (#1055)

Orientiert am Academy-Kurs „Management“ (BROICH.MANAGEMENTMODELL): Regelkreis, Management Book als Verdichtung, Lenkungsausschuss als Regelschleife („lokal vor übergreifend“, vier Fragen, Handlungsfähigkeit als Maßstab).

- **Dashboard** (`/management-book`): Cockpit über alle sichtbaren Books – Kennzahlen, nächste Sitzungen, offene Beschlüsse, Top-Risiken, Book-Liste.
- **Book** (`/books/{id}?tab=…`): Übersicht (Regelkreis mit 8 Stationen aus den Daten, Kennzahlen, Risikoprofil, was klemmt) · Book (Kapitel: Herkunft, Standort, Mission, Vision, Prioritäten, Geschäftsfelder, Grundsätze, Fähigkeiten, Zusammenarbeit – versioniert) · Strategie (Fokusräume, Transformation Map, Regnose/Forecasts) · Risiken (Risikobereitschaft, Heatmap, Register) · Gremien.
- **Gremium** (`/committees/{id}`) und **Sitzung** (`/meetings/{id}`): Mitglieder, Sitzungen, offene Beschlüsse; Agenda mit dem Leitfaden des Gremium-Formats (LA: vier Fragen und sechs Handlungsfähigkeits-Kriterien; Strategiemeeting: Ausgangslage/Richtungsfrage; Board: Lage/Vorlage/Abweichung), Beschlüsse, Teilnehmer, Protokoll, Unterlagen.
- Kennzahlen kommen aus `Services/BookInsights`. Neue Felder: Kapitel als `strategic_documents`-Typen (auch über `strategy.GET/PUT`), Agenda-Punkte `goal/observation/obstacle/local_solution/system_support/organization_entity_id/outcome_checks` (auch über `agenda_items.*`).
- Tests: `tests/run-in-taiste.sh tests/Feature/Ui*Test.php` (HTTP-Render + Aktionen gegen sqlite).

## Formate je Gremientyp und Verortung der Gremien

Alle Gremien teilen ein Datenmodell (Gremium, Sitzung, Agenda, Beschluss, Protokoll). Das **Format** je `CommitteeType` (`src/Formats/*`, `$committee->format()`) legt Verhalten und Sprache fest – keine eigenen Tabellen je Gremium:

| Typ | Format | Agenda-Arten (`kind`) | Leitfaden-Spalten | Beschluss-Arten (`kind`) | Erfolgsmessung |
|---|---|---|---|---|---|
| `steering_committee` | Regulation | regulation, info | goal, observation, obstacle, local_solution, system_support (vier Fragen) | measure, return_to_unit, escalation | Handlungsfähigkeit (6 Kriterien) |
| `strategy_meeting` | Richtung | assessment, direction, info | observation (Ausgangslage), goal (Richtungsfrage) | direction, objective, guardrail, focus_area | Richtungsklarheit (direction_clear, priorities_set, trade_offs_named, book_updated, communication_planned) |
| `board` | Kontrolle & Freigabe | report, approval, info | observation (Lage), goal (Vorlage), obstacle (Abweichung) | approval, condition, rejection | Entscheidungsklarheit (situation_understood, risks_assessed, approvals_given, conditions_with_deadline, followup_clear) |
| `other` | Besprechung | topic, info | observation, goal | decision, task | Ergebnis (3 Kriterien) |

`kind` an Agenda-Punkt und Beschluss ist optional (null = Standard-Art). `info`-Punkte haben keinen Leitfaden und zählen nicht in die Messung. Sitzungsseite, Gremium-Seite, Tools und Kennzahlen lesen das Format; die Tools prüfen `kind` und `outcome_checks` dagegen. `management-book.committees.GET id=…` liefert das vollständige Format für Agenten. **Handlungsfähigkeit** (Kennzahlen `mb_meeting_capability_percent`, `mbc_meeting_capability_percent`) zählt nur Lenkungsausschuss-Sitzungen.

**Gremium = Organisations-Knoten:** Das Gremium IST ein Knoten der Organisation (Typ „Board / Gremium“, Code `board`); das Book ist sein Betriebssystem. Spalte `management_book_committees.organization_entity_id` (genau ein Knoten, eindeutig je Team), daraus gepflegt die Bridge-Verknüpfung (Alias `management_book_committee`) für Links, Kennzahlen `mbc_*` und Zeiten. Externe Verknüpfungen (`organization.entity.link.POST`) ziehen die Spalte nach.

- **Am Knoten (Single Source):** Name, Beschreibung, Mitglieder und Vorsitz. Mitglieder = Beziehungen zum Knoten: Person „ist Teil von“ (`is_part_of`) → Mitglied, „führt an“ (`manages`) → Vorsitz (genau einer), Agent „ist aktiv in“ (`is_active_in`). Plattform-Konto über `linked_user_id` am Personen-/Agenten-Knoten; Personen ohne Konto sind vollwertige Mitglieder (auch als Sitzungs-Teilnehmer: `management_book_meeting_attendees.organization_entity_id`). Änderungen über Book-UI/Tools schreiben in die Organisation (Schreibrecht auf den Knoten nötig).
- **Im Book:** Typ/Format, Rhythmus, Status, verantwortlicher Agent, Sitzungen, Beschlüsse. Die Book-Spalten `name`/`description` und die Tabelle `management_book_committee_members` sind nur noch Fallback für Gremien ohne Knoten (Übergang) bzw. ohne eingerichtete Organisation.
- **Anlegen:** vorhandenen Gremien-Knoten aktivieren (Gremien-Tab zeigt freie Gremien-Knoten unter der Einheit) oder neu anlegen – Default unter dem Container „Governance“ der Einheit, an der das Book hängt (wird bei Bedarf angelegt). Tool: `management-book.committees.POST` mit `organization_entity_id` oder `create_node {name, parent_entity_id?, description?}`.
- **Zeit:** Zeit auf ein Gremium oder eine seiner Sitzungen (`organization_time_entries` mit Kontext Gremium bzw. Sitzung, z. B. „Zeit buchen“ auf der Sitzungsseite) rollt über `timeTrackableCascades()` zum Gremien-Knoten und die Hierarchie hinauf.

## Präsentationen an Sitzung und Gremium (Slides, lose gekoppelt)

Ist das Modul `slides` installiert, zeigen Sitzungs- und Gremium-Seite einen Abschnitt „Präsentationen“ (Livewire `management-book.linked-decks`): verknüpfte Decks mit Vorschau der ersten Folie (isoliertes iframe aus Slides), Folienanzahl, Status und „Präsentieren“ / „Referentenansicht“ / „Öffnen“ / „PDF“ (je nach vorhandener Slides-Route). Decks anlegen, vorhandene verknüpfen oder lösen. Verknüpft wird über `slides_deck_links` mit den Morph-Aliasen `management_book_meeting` bzw. `management_book_committee` (neu registriert). Agenten bereiten Decks über `slides.*` vor (`slides.links.POST` mit diesen Aliasen); die Vorschau prüft per Polling die Deck-Revision und aktualisiert sich live. Ohne Slides bleibt der Abschnitt ausgeblendet.

Tests: `SLIDES_MODULE_PATH=<slides> tests/run-in-taiste.sh tests/Feature/UiSlidesIntegrationTest.php` (ohne die Variable werden die Slides-Fälle übersprungen).
