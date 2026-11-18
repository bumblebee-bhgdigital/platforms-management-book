<?php

/**
 * Strategy Web Routes
 * 
 * Diese Datei definiert alle Web-Routes für das Modul.
 * 
 * WICHTIG FÜR LLMs:
 * - Routes werden automatisch mit dem Modul-Prefix versehen (aus Config)
 * - Middleware wird automatisch hinzugefügt (web, auth, etc.)
 * - Route-Namen sollten mit dem Modul-Prefix beginnen
 * 
 * BEISPIEL:
 * Route::get('/', Dashboard::class)->name('strategy.dashboard');
 * 
 * Wird zu: /strategy/ (wenn prefix = 'strategy')
 * 
 * @see Platform\Core\Routing\ModuleRouter für Details
 */

use Platform\Strategy\Livewire\Dashboard;
use Platform\Strategy\Livewire\Showcase;

/**
 * Dashboard Route
 *
 * Hauptübersicht des Moduls
 */
Route::get('/', Dashboard::class)->name('strategy.dashboard');

/**
 * nx-Showcase Route
 *
 * Lebendes Verzeichnis aller x-nx-* Komponenten (Design-Referenz).
 */
Route::get('/showcase', Showcase::class)->name('strategy.showcase');

/**
 * Weitere Routes hinzufügen:
 *
 * Route::get('/entities', Entity\Index::class)->name('strategy.entities.index');
 * Route::get('/entities/{entity}', Entity\Show::class)->name('strategy.entities.show');
 */
