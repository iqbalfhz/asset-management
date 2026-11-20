<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('Kesehatan sistem')"
        :description="__('Hanya terlihat oleh Super Admin. Semua Normal berarti tidak ada yang perlu dilakukan.')"
    >
        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($this->checks() as $key => $check)
                <li class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3">
                    <div class="min-w-0 flex-1">
                        @if (isset($check['url']))
                            <x-filament::link :href="$check['url']" class="text-sm font-medium">{{ $check['label'] }}</x-filament::link>
                        @else
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $check['label'] }}</p>
                        @endif

                        @if (filled($check['detail'] ?? null))
                            <p class="mt-0.5 break-words text-sm text-gray-600 dark:text-gray-300">{{ $check['detail'] }}</p>
                        @endif
                    </div>

                    @if ($key === 'jobs')
                        {{ $this->retryFailedJobsAction }}
                    @endif

                    <span class="text-sm tabular-nums text-gray-950 dark:text-white">{{ $check['value'] }}</span>

                    <x-filament::badge :color="$check['ok'] ? 'success' : 'danger'">
                        {{ $check['ok'] ? __('Normal') : __('Perlu dicek') }}
                    </x-filament::badge>
                </li>
            @endforeach
        </ul>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
