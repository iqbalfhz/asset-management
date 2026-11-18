@if (\App\Http\Controllers\MicrosoftLoginController::isConfigured())
    <div class="grid gap-4">
        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
            <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
            {{ __('atau') }}
            <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
        </div>

        <x-filament::button tag="a" :href="route('login.microsoft')" color="gray" class="w-full">
            {{ __('Masuk dengan akun Microsoft kantor') }}
        </x-filament::button>
    </div>
@endif
