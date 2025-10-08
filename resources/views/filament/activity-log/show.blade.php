@php($p = $record->properties ?? collect())
<div class="space-y-2 text-sm">
  <div><b>Waktu:</b> {{ $record->created_at->timezone(auth()->user()->timezone ?? config('app.timezone','Asia/Jakarta'))->format('d/m/Y H:i') }}</div>
  <div><b>User:</b> {{ optional($record->causer)->name }} (ID: {{ $record->causer_id }})</div>
  <div><b>Aksi:</b> {{ $record->event }}</div>
  <div><b>Objek:</b> {{ class_basename($record->subject_type) }} #{{ $record->subject_id }}</div>
  <div><b>Deskripsi:</b> {{ $record->description }}</div>
  <div><b>IP:</b> {{ data_get($p,'ip') }} | <b>UA:</b> {{ Str::limit(data_get($p,'user_agent'),120) }}</div>
  @if ($diff = data_get($p, 'attributes'))
    <div class="mt-2">
      <b>Perubahan:</b>
      <pre class="bg-gray-50 p-3 rounded border overflow-x-auto">{{ json_encode([
        'new' => $p['attributes'] ?? [],
        'old' => $p['old'] ?? [],
      ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
  @endif
  @if ($route = data_get($p,'route'))
    <div><b>Route:</b> {{ $route }}</div>
  @endif
  @if ($url = data_get($p,'url'))
    <div><b>URL:</b> {{ $url }}</div>
  @endif
</div>
