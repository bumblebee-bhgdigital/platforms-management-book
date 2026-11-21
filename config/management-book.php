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

    /*
    |--------------------------------------------------------------------------
    | Academy-Verweise (Kurs „Management“, BROICH.MANAGEMENTMODELL)
    |--------------------------------------------------------------------------
    | UUIDs der Lessons im Academy-Modul. Die Orientierungsspalte verlinkt sie,
    | wenn die Route academy.lessons.show existiert. Pro Instanz überschreibbar.
    */
    'academy' => [
        'lessons' => [
            'regnose' => ['uuid' => '01a1cab6-a3c3-7088-ae8f-c9cb903e994b', 'title' => 'Prognose oder Regnose'],
            'zukunftsbild' => ['uuid' => '01a1cab7-2419-78eb-aa1a-83af0388ead8', 'title' => 'Ein Zukunftsbild, das man sehen kann'],
            'betriebssystem' => ['uuid' => '01a1cab7-a412-7a36-9e7f-4bf649061d63', 'title' => 'Das Betriebssystem'],
            'hilfe_statt_macht' => ['uuid' => '01a1cab8-362b-7dc8-a6e2-a13a43df58bf', 'title' => 'Hilfe statt Macht'],
            'regelkreis' => ['uuid' => '01a1cab8-c56a-7f38-9207-807d4f85795a', 'title' => 'Der Regelkreis'],
            'kick_off' => ['uuid' => '01a1cab9-6bf7-7f92-af72-b507eb172d35', 'title' => 'Kick-off und Fokusplanung'],
            'lenkungsausschuss' => ['uuid' => '01a1cab9-fc0b-72fc-ba46-be29fb107835', 'title' => 'Der Lenkungsausschuss'],
            'vier_fragen' => ['uuid' => '01a1caba-7c98-7ad6-932e-c8dfc977a281', 'title' => 'Die vier Fragen'],
            'roter_faden' => ['uuid' => '01a1caba-ed99-7156-978e-a9e752ac43b8', 'title' => 'Der rote Faden'],
        ],
    ],
];
