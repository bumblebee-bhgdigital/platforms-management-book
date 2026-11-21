{{-- Ein Waypoint (Meilenstein) auf der Transformation Map. Erwartet $m, $fa, $mStyle, $map, $canEdit. --}}
@php $st = $mStyle($m); $overdue = isset($map['overdue'][$m->id]); @endphp
<{{ $canEdit ? 'button type=button' : 'div' }}
    @if($canEdit) wire:click="openItem('milestone', {{ $fa->id }}, {{ $m->id }})" @endif
    wire:key="wp-{{ $m->id }}"
    title="{{ $m->title }} · {{ $m->status->label() }}{{ $overdue ? ' · überfällig' : '' }}{{ $m->target_quarter ? '' : ($m->target_year ? ' · nur Jahr' : '') }}"
    class="flex w-full items-start gap-1.5 rounded-[6px] border px-2 py-1 text-left text-xs leading-snug transition-colors {{ $canEdit ? 'hover:bg-[color:var(--nx-hover)]' : '' }} {{ $m->status === \Platform\ManagementBook\Enums\MilestoneStatus::CANCELLED ? 'line-through text-[color:var(--nx-faint)]' : 'text-[color:var(--nx-text)]' }}"
    style="background: {{ $st['bg'] }}; border-color: {{ $st['border'] }};">
    <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $st['dot'] }}"></span>
    <span class="min-w-0 break-words">{{ $m->title }}</span>
    @if($overdue)
        @svg('heroicon-o-clock', 'ml-auto mt-0.5 w-3 h-3 shrink-0 text-[color:var(--nx-warning)]')
    @elseif($m->status === \Platform\ManagementBook\Enums\MilestoneStatus::REACHED)
        @svg('heroicon-o-check', 'ml-auto mt-0.5 w-3 h-3 shrink-0 text-[color:var(--nx-success)]')
    @endif
</{{ $canEdit ? 'button' : 'div' }}>
