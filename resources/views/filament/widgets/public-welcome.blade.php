<div class="mx-auto w-full max-w-5xl">
    <div class="rounded-xl border border-gray-200 bg-white px-6 py-5 shadow-sm">
        {{-- dari row → kolom, supaya tombol di bawah --}}
        <div class="flex flex-col gap-4">
            {{-- teks bisa melebar penuh --}}
            <div class="min-w-0">
                <h3 class="text-2xl sm:text-3xl font-semibold text-gray-900 leading-tight">
                    Selamat datang 👋
                </h3>

                <p class="mt-2 text-gray-600">
                    Ini adalah beranda publik. Anda bisa menelusuri dokumen tanpa login.
                    <br>
                    <b>Login</b> hanya untuk User yang sudah ditambahkan oleh Admin. Jika Anda butuh akses Login,
                    silakan hubungi Admin.
                </p>
            </div>

            {{-- tombol diposisikan di bawah teks --}}
            <div class="flex flex-wrap gap-3">
                <x-filament::button tag="a" href="/berkas" color="gray">
                    Lihat Dokumen
                </x-filament::button>

                <x-filament::button tag="a" href="/admin/login">
                    Masuk Admin/Editor
                </x-filament::button>
            </div>
        </div>
    </div>
</div>
