@php($active = request()->is('admin/login'))
<div class="mt-3 border-t border-gray-200 pt-3 px-3 flex justify-center">
  <x-filament::button
      tag="a"
      href="{{ url('/admin/login') }}"
      color="primary"
      icon="heroicon-m-arrow-right-on-rectangle"
      icon-position="before"
      class="rounded-full justify-center gap-2 px-4 whitespace-nowrap"
  >
      Masuk User
  </x-filament::button>
</div>
