@php
    use Filament\Facades\Filament;
    use Carbon\Carbon;

    $user = Filament::auth()->user();
    $name = $user?->name ?? 'Admin';
    
    // Logic Role Label
    $roleLabel = $user?->hasRole('Admin') ? 'Administrator' : ($user?->hasRole('Editor') ? 'Editor' : 'Staff');
    
    // Logic Sapaan Waktu
    $hour = Carbon::now()->hour;
    $greeting = match(true) {
        $hour >= 3 && $hour < 11 => 'Selamat Pagi',
        $hour >= 11 && $hour < 15 => 'Selamat Siang',
        $hour >= 15 && $hour < 18 => 'Selamat Sore',
        default => 'Selamat Malam',
    };
@endphp

<div class="col-span-full">
    {{-- Container Utama --}}
    <div class="relative overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        
        {{-- 1. BACKGROUND GRADASI (Dipertebal supaya "Kelihatan") --}}
        {{-- Opacity dinaikkan ke 0.15 supaya warnanya keluar --}}
        <div class="absolute inset-0 pointer-events-none" 
             style="background: linear-gradient(110deg, #ffffff 40%, rgba(var(--primary-500), 0.15) 100%);">
        </div>

        {{-- 2. DEKORASI LINGKARAN (Diperbesar & Dipertebal) --}}
        <div class="absolute -right-20 -top-20 h-96 w-96 rounded-full pointer-events-none opacity-30"
             style="background: radial-gradient(circle, rgba(var(--primary-600), 0.2) 0%, transparent 70%);">
        </div>

        {{-- KONTEN UTAMA --}}
        <div class="relative flex flex-col items-center gap-6 p-6 md:flex-row md:gap-12 md:p-10">
            
            {{-- BAGIAN FOTO --}}
            <div class="shrink-0">
                <div class="relative">
                    @if ($user)
                        <img 
                            src="{{ Filament::getUserAvatarUrl($user) }}" 
                            alt="{{ $name }}" 
                            class="h-28 w-28 rounded-full border-[5px] border-white shadow-xl object-cover dark:border-gray-800"
                        >
                    @else
                        <div class="flex h-28 w-28 items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800 border-[5px] border-white dark:border-gray-700 shadow-xl">
                            <x-filament::icon icon="heroicon-m-user" class="h-14 w-14 text-gray-400"/>
                        </div>
                    @endif
                    <span class="absolute bottom-2 right-2 h-5 w-5 rounded-full border-[3px] border-white bg-green-500 dark:border-gray-900 shadow-sm"></span>
                </div>
            </div>

            {{-- BAGIAN TEXT (Full Rata Kiri di Desktop) --}}
            <div class="flex-1 text-center md:text-left">
                
                {{-- Role & Tanggal --}}
                <div class="mb-4 flex flex-wrap items-center justify-center gap-3 md:justify-start">
                    <span class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-700 ring-1 ring-inset ring-primary-700/10 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-500/20">
                        {{ $roleLabel }}
                    </span>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ Carbon::now()->translatedFormat('l, d F Y') }}
                    </span>
                </div>

                {{-- Sapaan --}}
                <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                    {{ $greeting }}, <br class="block sm:hidden">
                    <span style="color: rgb(var(--primary-600));">{{ $name }}!</span> 👋
                </h2>
                
                {{-- Deskripsi --}}
                <p class="mt-4 text-base text-gray-500 dark:text-gray-400 leading-relaxed max-w-full md:pr-12">
                    Selamat datang di <b>Dashboard Admin</b>. Pantau statistik dan kelola dokumen dengan mudah melalui panel ini.
                </p>

                <div class="h-4"></div>

                {{-- TOMBOL (Jarak diperlebar jadi mt-12) --}}
                <div class="mt-16 flex flex-wrap items-center justify-center gap-4 md:justify-start">
                    <x-filament::button 
                        tag="a" 
                        href="{{ route('filament.admin.pages.dashboard') }}" 
                        icon="heroicon-m-arrow-path"
                        color="primary"
                        size="md"
                    >
                        Refresh Data
                    </x-filament::button>

                    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                        @csrf
                        <x-filament::button 
                            type="submit" 
                            color="gray" 
                            icon="heroicon-m-power"
                            size="md"
                            outlined
                        >
                            Log Out
                        </x-filament::button>
                    </form>
                </div>
            </div>
            
        </div>
    </div>
</div>