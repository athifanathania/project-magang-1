<div class="mt-3 text-center text-sm text-gray-600">
    Login ini khusus untuk <b>Admin</b> dan <b>Editor</b>.
    Jika Anda membutuhkan akses Editor, silakan hubungi Admin untuk dibuatkan akun.
</div>

{{-- Tombol kembali ke Dashboard viewer (panel public) --}}
<div class="mt-4 flex justify-center">
    <x-filament::button
        tag="a"
        href="{{ route('filament.public.pages.dashboard') }}"  {{-- public dashboard --}}
        color="gray"
        icon="heroicon-m-home"
    >
        Kembali ke Beranda
    </x-filament::button>
</div>
