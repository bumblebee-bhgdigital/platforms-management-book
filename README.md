# platforms-management-book

Plattform-Modul: Management Book (Strategie, Analysen, Risiken, Gremien/Sitzungen).
Gerüst aus `martin3r-me/platform-module-template`; noch keine Fachlogik. Namespace `Platform\ManagementBook`, Paket `martin3r/platform-management-book`. Doku zum Gerüst: `LLM_GUIDE.md`, `DESIGN.md`.

## Strategie im Book (#1053)

Je Management Book: Mission/Vision (versioniert), Forecasts (+Versionen/Regnose, Zieljahr), Fokusräume (empfohlen 5–7) mit den drei Spalten Zielbilder / Hindernisse / Meilensteine (je Leitfrage). Felder und Verhalten orientieren sich 1:1 an den `organization.*`-Strategie-Tools (book-FK statt Entity). Keine Datenmigration.

MCP-Tools (Präfix `management-book.`): `strategy.GET` (aggregierte Sicht, analog `organization.strategy.GET`), `strategy.PUT` (Mission/Vision), `forecasts.GET/POST/PUT/DELETE`, `forecasts.versions.POST`, `focus_areas.*`, `vision_images.*`, `obstacles.*`, `milestones.*` (je GET/POST/PUT/DELETE). Rechte werden vom Management Book geerbt.
