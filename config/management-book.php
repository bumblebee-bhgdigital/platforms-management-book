<?php

/**
 * Management Book Configuration
 * 
 * Diese Config-Datei definiert die Konfiguration für das Modul.
 * 
 * WICHTIG FÜR LLMs:
 * - Ersetze "management-book" durch deinen Modul-Namen
 * - Ersetze "ManagementBook" durch deinen Modul-Namen (PascalCase)
 * - Alle Routes müssen mit dem Modul-Prefix beginnen
 * 
 * @see Platform\Core\PlatformCore::registerModule() für Details zur Modul-Registrierung
 */

return [
    /**
     * Routing-Konfiguration
     * 
     * 'mode': 'path' = /management-book/... (Standard)
     *         'subdomain' = management-book.domain.com/... (Alternative)
     * 'prefix': URL-Präfix für alle Routes
     */
    'routing' => [
        'mode' => env('MANAGEMENT_BOOK_MODE', 'path'),
        'prefix' => 'management-book',
    ],
    
    /**
     * Guard für Authentication
     * Standard: 'web'
     */
    'guard' => 'web',

    /**
     * Navigation-Konfiguration
     * 
     * Definiert, wie das Modul in der Hauptnavigation erscheint.
     * 'route': Route-Name für den Link
     * 'icon': Heroicon-Name (ohne heroicon-o- Präfix)
     * 'order': Sortier-Reihenfolge (niedrigere Zahlen = weiter oben)
     */
    'navigation' => [
        'route' => 'management-book.dashboard',
        'icon'  => 'heroicon-o-book-open',
        'order' => 100, // Hohe Zahl = weiter unten in der Navigation
    ],

    /**
     * Sidebar-Konfiguration
     * 
     * Definiert die Sidebar-Struktur für das Modul.
     * 
     * Struktur:
     * - 'group': Gruppenname (optional)
     * - 'items': Array von Sidebar-Items
     *   - 'label': Anzeige-Text
     *   - 'route': Route-Name
     *   - 'icon': Heroicon-Name
     * 
     * Alternative: 'dynamic' für dynamische Listen (z.B. aus Datenbank)
     *   - 'model': Model-Klasse
     *   - 'team_based': true/false (nach Team filtern)
     *   - 'order_by': Sortier-Feld
     *   - 'route': Basis-Route (wird mit ID erweitert)
     *   - 'icon': Icon für alle Items
     *   - 'label_key': Feldname für Label
     */
    'sidebar' => [
        [
            'group' => 'Management Books',
            'items' => [
                [
                    'label' => 'Übersicht',
                    'route' => 'management-book.dashboard',
                    'icon'  => 'heroicon-o-book-open',
                ],
                [
                    'label' => 'nx-Showcase',
                    'route' => 'management-book.showcase',
                    'icon'  => 'heroicon-o-swatch',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kaskade (Platzhalter)
    |--------------------------------------------------------------------------
    | Management Book -> Gremien -> Sitzungen -> Agenda/Unterlagen/Protokoll/Beschlüsse.
    | Sobald das Gremium-Model existiert: FQCN hier eintragen. Es braucht eine Spalte
    | `management_book_id` (FK auf management_books). ManagementBook::committees() nutzt
    | dann diese Klasse; bis dahin liefert die Relation eine leere Menge.
    */
    'cascade' => [
        'committee_model' => env('MANAGEMENT_BOOK_COMMITTEE_MODEL', \Platform\ManagementBook\Models\ManagementBookCommittee::class),
    ],
];
