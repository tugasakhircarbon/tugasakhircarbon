<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-gray-500">{{ $title }}</p>
            <h3 class="text-2xl font-bold text-dark">
                {{ $value }}
                @if(isset($unit))
                    <span class="text-sm font-normal">{{ $unit }}</span>
                @endif
            </h3>
        </div>
<div class="p-3 w-12 h-12 rounded-full {{ $iconBgClass }} {{ $iconTextClass }} flex items-center justify-center">
    <i class="{{ $iconClass }} text-xl"></i>
</div>
    </div>
    @if(isset($change))
    <div class="mt-4">
        <span class="{{ $changeClass }} text-sm font-medium">
            <i class="{{ $changeIcon }}"></i> {{ $change }}
        </span>
    </div>
    @endif
</div>
</create_file>
