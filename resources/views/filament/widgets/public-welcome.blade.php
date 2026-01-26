<div class="col-span-full">
    <div class="relative overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        
        {{-- Background Pattern Sederhana --}}
        <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,white,rgba(255,255,255,0.6))] dark:bg-grid-slate-700/25 dark:[mask-image:linear-gradient(0deg,rgba(255,255,255,0.1),rgba(255,255,255,0.5))]"></div>

        <div class="relative px-6 py-8 sm:px-10 sm:py-12 flex flex-col md:flex-row items-center gap-8">
            
            {{-- IKON BESAR / ILUSTRASI --}}
            <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
                <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="h-12 w-12" />
            </div>

            {{-- KONTEN TEKS --}}
            <div class="text-center md:text-left flex-1">
                <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Document Management Control
                </h2>
                <p class="mt-3 text-lg text-gray-600 dark:text-gray-400 leading-relaxed">
                    Selamat datang di portal dokumen publik. Anda dapat menelusuri SOP, Instruksi Kerja, dan Formulir tanpa perlu login.
                </p>

                <div class="mt-2 text-sm text-gray-500 dark:text-gray-500 bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg border border-gray-100 dark:border-gray-700 inline-block text-left">
                    <span class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-information-circle" class="w-4 h-4 text-gray-400"/>
                        <span>Login hanya diperlukan untuk <b>Admin</b> & <b>Editor</b>.</span>
                    </span>
                </div>

                {{-- TOMBOL AKSI --}}
                <div class="mt-8 flex flex-wrap items-center justify-center md:justify-start gap-4">
                    <x-filament::button 
                        tag="a" 
                        href="/berkas" 
                        size="lg"
                        icon="heroicon-m-folder-open"
                    >
                        Telusuri Dokumen
                    </x-filament::button>

                    <x-filament::button 
                        tag="a" 
                        href="/admin/login" 
                        color="gray"
                        size="lg"
                        icon="heroicon-m-lock-closed"
                    >
                        Login Staff
                    </x-filament::button>
                </div>
            </div>

        </div>
    </div>
</div>