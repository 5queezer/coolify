@props([
    'status' => 'Hibernated',
    'noLoading' => false,
])
<div class="flex items-center">
    @if (!$noLoading)
        <x-loading wire:loading.delay.longer />
    @endif
    <span wire:loading.remove.delay.longer class="flex items-center">
        <div class="badge badge-warning"></div>
        <div class="pl-2 pr-1 text-xs font-bold dark:text-warning" title="Sablier hibernated this resource. It will wake on the next matching request.">
            Hibernated
        </div>
    </span>
</div>
