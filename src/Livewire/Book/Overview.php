<?php

namespace Platform\ManagementBook\Livewire\Book;

use Livewire\Component;
use Platform\ManagementBook\Livewire\Concerns\LoadsBook;
use Platform\ManagementBook\Services\BookInsights;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Cockpit eines Books: Titelblatt (Vision/Mission), Regelkreis als Kreis (acht Stationen mit abgeleitetem Zustand),
 * Kennzahlen mit Einordnung, Risikoprofil, nächste Sitzung und das, was gerade klemmt.
 */
class Overview extends Component
{
    use LoadsBook;

    /** Ausgewählte Regelkreis-Station (Index 0–7). */
    public int $station = 0;

    public function selectStation(int $index): void
    {
        $this->station = max(0, min(7, $index));
    }

    public function render()
    {
        $book = $this->book();
        $insights = app(BookInsights::class)->forBook($book);

        $nodeIds = app(ManagementBookService::class)->nodeIds($book);
        $nodeNames = $nodeIds === [] ? [] : OrganizationEntity::whereIn('id', $nodeIds)->pluck('name')->all();

        return view('management-book::livewire.book.overview', [
            'book' => $book,
            'i' => $insights,
            'states' => BookInsights::STATES,
            'nodeNames' => $nodeNames,
            'ring' => $this->ringGeometry($insights['cycle']),
        ]);
    }

    /**
     * Geometrie des Regelkreises (SVG, viewBox -70 20 740 560): acht Stationen im Uhrzeigersinn ab 12 Uhr,
     * Bögen mit Pfeil zwischen den Stationen, Beschriftung außerhalb des Rings.
     *
     * @return array{cx:int,cy:int,r:int,nodes:array<int,array>,arcs:array<int,string>}
     */
    private function ringGeometry(array $cycle): array
    {
        $cx = 300;
        $cy = 300;
        $r = 170;
        $nodeR = 24;
        $n = count($cycle);
        $nodes = [];
        $arcs = [];

        foreach ($cycle as $idx => $s) {
            $a = deg2rad(-90 + $idx * (360 / $n));
            $x = $cx + $r * cos($a);
            $y = $cy + $r * sin($a);
            $cos = cos($a);
            $sin = sin($a);
            $lines = explode("\n", wordwrap($s['name'], 14, "\n", false));
            $lr = $r + 46;
            $lx = $cx + $lr * $cos;
            $ly = $cy + $lr * $sin;
            $anchor = $cos > 0.3 ? 'start' : ($cos < -0.3 ? 'end' : 'middle');
            // Zeilen so setzen, dass der Block oben über, unten unter und seitlich mittig zur Station steht
            $lineH = 21;
            $first = match (true) {
                $sin < -0.7 => $ly - ($lineH * (count($lines) - 1)),
                $sin > 0.7 => $ly + 8,
                default => $ly + 5 - ($lineH * (count($lines) - 1)) / 2,
            };
            $nodes[] = [
                'x' => round($x, 1), 'y' => round($y, 1), 'r' => $nodeR,
                'lx' => round($lx, 1), 'ly' => round($first, 1), 'anchor' => $anchor, 'lines' => $lines, 'lineH' => $lineH,
            ];

            // Bogen zur nächsten Station (Abstand zum Knoten ~ 14°)
            $gap = deg2rad(14);
            $a1 = $a + $gap;
            $a2 = deg2rad(-90 + ($idx + 1) * (360 / $n)) - $gap;
            $arcs[] = sprintf(
                'M %.1f %.1f A %d %d 0 0 1 %.1f %.1f',
                $cx + $r * cos($a1), $cy + $r * sin($a1), $r, $r, $cx + $r * cos($a2), $cy + $r * sin($a2)
            );
        }

        return ['cx' => $cx, 'cy' => $cy, 'r' => $r, 'nodes' => $nodes, 'arcs' => $arcs];
    }
}
