@php
    use Filament\Facades\Filament;
    $user = Filament::auth()->user();
    $name = $user?->name ?? 'Admin';
    $roleLabel = $user?->hasRole('Admin') ? 'Admin' : ($user?->hasRole('Editor') ? 'Editor' : 'Admin/Editor');
@endphp

<div class="mx-auto w-full max-w-5xl">
  <div class="rounded-xl border border-gray-200 bg-white px-6 py-5 shadow-sm">
    <div class="flex flex-col gap-3">
      {{-- BARIS: avatar + judul --}}
      <div class="flex items-center gap-3">
        @if ($user)
          <img src="{{ Filament::getUserAvatarUrl($user) }}" alt="{{ $name }}" class="h-10 w-10 rounded-full">
        @endif

        <h3 class="text-2xl sm:text-3xl font-semibold text-gray-900 leading-tight">
          Selamat datang {{ $name }} 👋
        </h3>
      </div>

      {{-- DESKRIPSI di BAWAH ikon/judul, diratakan dengan teks (indent = lebar avatar + gap) --}}
      <p class="text-gray-600 text-sm sm:ml-14">
        Ini adalah beranda <b>{{ $roleLabel }}</b>. Gunakan menu di kiri untuk mengelola data.
      </p>

      {{-- Tombol di bawah deskripsi, masih selaras dengan teks --}}
      <div class="flex flex-wrap gap-3 sm:ml-14">
        <x-filament::button tag="a" href="{{ route('filament.admin.pages.dashboard') }}" color="gray">
          Dashboard
        </x-filament::button>

        <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
          @csrf
          <x-filament::button type="submit">Sign out</x-filament::button>
        </form>
      </div>
    </div>
  </div>
</div>
