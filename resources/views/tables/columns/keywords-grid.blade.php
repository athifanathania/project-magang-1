@php
    $keywords = $getState() ?? [];

    if (is_string($keywords)) {
        $decoded = json_decode($keywords, true);
        $keywords = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : preg_split('/\s*,\s*/u', $keywords, -1, PREG_SPLIT_NO_EMPTY);
    }

    $keywords = collect($keywords)
        ->filter(fn ($v) => is_string($v) && trim($v) !== '')
        ->map(fn ($v) => trim($v))
        ->values();
@endphp

@once
<style>
  /* Grid + chip kecil (amber) untuk kolom "Kata Kunci" di tabel Berkas */
  .kw-grid{display:flex;flex-wrap:wrap;gap:.3rem;align-items:flex-start}
  .kw-pill{
    display:inline-flex;align-items:center;white-space:nowrap;
    font-size:.625rem;        /* ≈ 10px -> kecil */
    line-height:1.1;
    padding:.0625rem .375rem; /* 1px 6px */
    font-weight:600;
    border-radius:.375rem;
    background:#fffbeb;       /* amber-50 */
    color:#92400e;            /* amber-700 */
    border:1px solid #fde68a; /* amber-200 */
  }
</style>
@endonce

@if ($keywords->isNotEmpty())
  <div class="kw-grid">
    @foreach ($keywords as $tag)
      <span class="kw-pill">{{ $tag }}</span>
    @endforeach
  </div>
@else
  <span class="text-gray-400">-</span>
@endif
