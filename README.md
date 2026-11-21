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
