<?php

/**
 * Management Book Web Routes
 * 
 * Diese Datei definiert alle Web-Routes für das Modul.
 * 
 * WICHTIG FÜR LLMs:
 * - Routes werden automatisch mit dem Modul-Prefix versehen (aus Config)
 * - Middleware wird automatisch hinzugefügt (web, auth, etc.)
 * - Route-Namen sollten mit dem Modul-Prefix beginnen
 * 
 * BEISPIEL:
 * Route::get('/', Dashboard::class)->name('management-book.dashboard');
 * 
 * Wird zu: /management-book/ (wenn prefix = 'management-book')
 * 
 * @see Platform\Core\Routing\ModuleRouter für Details
 */

use Platform\ManagementBook\Livewire\Dashboard;
use Platform\ManagementBook\Livewire\Show;
use Platform\ManagementBook\Livewire\Showcase;

/**
 * Dashboard Route
 *
 * Hauptübersicht des Moduls
 */
Route::get('/', Dashboard::class)->name('management-book.dashboard');

// Übersicht aller Books (Alias auf dieselbe Komponente) und Detailseite.
// {managementBook} = Route-Model-Binding auf die ID (wie {plannerProject} im Planner).
Route::get('/books', Dashboard::class)->name('management-book.books.index');
Route::get('/books/{managementBook}', Show::class)->name('management-book.books.show');

/**
 * nx-Showcase Route
 *
 * Lebendes Verzeichnis aller x-nx-* Komponenten (Design-Referenz).
 */
Route::get('/showcase', Showcase::class)->name('management-book.showcase');

/**
 * Weitere Routes hinzufügen:
 *
 * Route::get('/entities', Entity\Index::class)->name('management-book.entities.index');
 * Route::get('/entities/{entity}', Entity\Show::class)->name('management-book.entities.show');
 */
