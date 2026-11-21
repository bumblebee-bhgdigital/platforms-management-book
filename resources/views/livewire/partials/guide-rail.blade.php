{{--
    Orientierungsspalte (rechts): worum es in diesem Bereich geht, warum, Leitsatz, Academy-Lessons.
    Weitere Rail-Inhalte (z. B. „Erste Schritte“) als eigenes Partial davor/danach einbinden.

    @include('management-book::livewire.partials.guide-rail', ['area' => 'strategy'])
--}}
@php $g = \Platform\ManagementBook\Support\Guide::for($area ?? 'overview'); @endphp
<aside class="space-y-5 text-sm">
    <div class="space-y-3">
        <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">{{ $g['eyebrow'] }}</div>
        <h3 class="m-0 text-base font-semibold leading-snug text-[color:var(--nx-text)]">{{ $g['title'] }}</h3>
        <p class="m-0 leading-relaxed text-[color:var(--nx-muted)]">{{ $g['lead'] }}</p>
        <p class="m-0 leading-relaxed text-[color:var(--nx-muted)]">{{ $g['why'] }}</p>
    </div>

    <figure class="m-0 border-l-2 border-[color:var(--nx-line-strong)] pl-3">
        <blockquote class="m-0 text-[15px] font-medium leading-snug text-[color:var(--nx-text)]">„{{ $g['principle'] }}“</blockquote>
        <figcaption class="mt-1 text-xs text-[color:var(--nx-faint)]">BROICH.MANAGEMENTMODELL</figcaption>
    </figure>

    @if($g['lessons'])
        <div class="space-y-1.5">
            <div class="text-xs font-medium text-[color:var(--nx-muted)]">Vertiefen in der Academy</div>
            @foreach($g['lessons'] as $lesson)
                @if($lesson['url'])
                    <a href="{{ $lesson['url'] }}" wire:navigate
                       class="flex items-center gap-2 rounded-[6px] px-2 py-1.5 text-[color:var(--nx-text)] transition-colors hover:bg-[color:var(--nx-hover)]">
                        @svg('heroicon-o-academic-cap', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                        <span class="truncate">{{ $lesson['title'] }}</span>
                        @svg('heroicon-o-arrow-up-right', 'ml-auto w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                    </a>
                @else
                    <div class="flex items-center gap-2 px-2 py-1.5 text-[color:var(--nx-muted)]">
                        @svg('heroicon-o-academic-cap', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                        <span class="truncate">{{ $lesson['title'] }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</aside>
