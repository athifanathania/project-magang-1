<?php

namespace App\Filament\Resources;

use Filament\Tables;
use Filament\Resources\Resource;
use Spatie\Activitylog\Models\Activity;
use Filament\Tables\Table;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\User;
use Illuminate\Support\Carbon;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Admin';
    protected static ?string $navigationLabel = 'Log History User';
    protected static ?int $navigationSort = 99;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->striped()
            ->columns([
                // 1. WAKTU
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable()
                    ->verticalAlignment('start'),

                // 2. USER
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->toggleable()
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2) // Batasi 2 baris
                    ->verticalAlignment('start'),

                // 3. AKSI
                Tables\Columns\TextColumn::make('event')
                    ->label('Aksi')
                    ->badge() 
                    ->searchable()
                    ->sortable()
                    // ->verticalAlignment('start')
                    // ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'view'                                                                         => 'primary', // View biasanya netral
                        'login', 'created', 'create', 'version_add', 'version_replace', 'version_reopen', 'download' => 'success', // Hijau
                        'updated', 'update', 'version_desc_update'                                     => 'warning', // Kuning/Oranye
                        'deleted', 'delete', 'version_delete', 'logout'                                => 'danger', // Merah
                        default                                                                        => 'primary', // Warna default jika tidak ada yang cocok
                    }),

                // 4. DESKRIPSI (Updated agar rapi ada '...')
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->wrap()
                    ->lineClamp(2) // Batasi 2 baris, sisanya '...'
                    ->tooltip(fn ($state) => $state) // Hover untuk lihat full text
                    ->searchable()
                    ->verticalAlignment('start')
                    ->extraCellAttributes(['style' => 'min-width: 250px;']), // Opsional: lebar minimum

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Objek')
                    ->getStateUsing(function (\Spatie\Activitylog\Models\Activity $record) {

                        if ($record->subject_type === \App\Models\User::class && $record->subject) {
                            return $record->subject->name . ' #' . ($record->subject->department ?? '-');
                        }

                        // --- 2. PRIORITAS UTAMA: Function Model (Live Data) ---
                        if ($record->subject && method_exists($record->subject, 'getActivityDisplayName')) {
                            return $record->subject->getActivityDisplayName();
                        }

                        // --- 3. Cek titipan label custom (Cadangan / Data Terhapus) ---
                        $label = $record->getExtraProperty('object_label');
                        if (filled($label)) {
                            return str_replace('Berkas: Event:', 'Event:', $label);
                        }

                        // --- 4. Fallback Terakhir ---
                        if (! $record->subject_type || ! $record->subject_id) return '-';
                        return class_basename($record->subject_type) . '#' . $record->subject_id;
                    })
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn ($state) => $state)
                    ->searchable() 
                    ->verticalAlignment('start')
                    ->color(fn ($record) => $record->subject_type === \App\Models\User::class ? 'info' : null),

                // 6. IP
                Tables\Columns\TextColumn::make('properties.ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault:true)
                    ->verticalAlignment('start'),

                // 7. ROUTE (Updated agar tidak merusak layout)
                Tables\Columns\TextColumn::make('properties.route')
                    ->label('Route')
                    ->wrap()
                    ->lineClamp(1) // URL panjang cukup 1 baris + ...
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault:true)
                    ->verticalAlignment('start'),

                Tables\Columns\TextColumn::make('properties->object_label')
                    ->label('Objek (search)')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
            ])
            ->filters([
                // --- 1. Filter Periode Cepat ---
                Tables\Filters\SelectFilter::make('period')
                    ->label('Periode Cepat')
                    ->options([
                        'today'      => 'Hari ini',
                        'yesterday'  => 'Kemarin',
                        'last7'      => '7 hari terakhir',
                        'this_month' => 'Bulan ini',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $v = $data['value'] ?? null;
                        if (!$v) return $query;
                        return match ($v) {
                            'today'      => $query->whereDate('created_at', Carbon::today()),
                            'yesterday'  => $query->whereDate('created_at', Carbon::yesterday()),
                            'last7'      => $query->where('created_at', '>=', Carbon::now()->subDays(7)->startOfDay()),
                            'this_month' => $query->whereMonth('created_at', Carbon::now()->month)
                                                ->whereYear('created_at', Carbon::now()->year),
                            default      => $query,
                        };
                    }),

                // --- 3. Filter Event/Aksi ---
                Tables\Filters\SelectFilter::make('event')
                    ->label('Jenis Aksi')
                    ->options([
                        'login'            => 'Login',
                        'logout'           => 'Logout',
                        'view'             => 'Melihat Halaman (View)', // Label diperjelas
                        'download'         => 'Download',
                        'created'           => 'Create (Tambah)',
                        'updated'           => 'Update (Edit)',
                        'deleted'           => 'Delete (Hapus)',
                        'version_add'      => 'Version Add',
                        'version_replace'  => 'Version Replace',
                        'version_reopen'   => 'Version Reopen',
                        'version_delete'   => 'Version Delete',
                        'version_desc_update' => 'Version Desc Update',
                    ]),

                // --- 4. Filter User ---
                Tables\Filters\Filter::make('by_user')
                    ->label('User')
                    ->form([
                        Forms\Components\Select::make('id')
                            ->label('Pilih User')
                            ->options(fn () => \App\Models\User::query()
                                ->orderBy('name')->pluck('name', 'id')->all()
                            )
                            ->searchable() // Tambahkan searchable agar user mudah dicari
                            ->preload()
                    ])
                    ->query(function (Builder $query, array $data) {
                        $id = $data['id'] ?? null;
                        if (!$id) return $query;
                        return $query->where('causer_type', \App\Models\User::class)
                            ->where('causer_id', $id);
                    })
                    ->indicateUsing(function (array $data) {
                        $id = $data['id'] ?? null;
                        if (!$id) return null;
                        $name = \App\Models\User::find($id)?->name;
                        return $name ? "User: {$name}" : null;
                    }),

                // --- 5. Filter Tipe Objek ---
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Tipe Objek')
                    ->options(fn () => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($full) => [$full => class_basename($full)])
                        ->sort()
                        ->all()
                    ),

                // --- 6. Filter Rentang Waktu Manual ---
                Tables\Filters\Filter::make('created_between')
                    ->label('Rentang Waktu')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('to')->label('Sampai')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : null;
                        $to   = filled($data['to']   ?? null) ? Carbon::parse($data['to'])->endOfDay()   : null;
                        if ($from) $query->where('created_at', '>=', $from);
                        if ($to)   $query->where('created_at', '<=', $to);
                        return $query;
                    }),
                    
                // --- 7. Filter Hanya Perubahan ---
                Tables\Filters\TernaryFilter::make('with_diff')
                    ->label('Hanya yg ada perubahan data')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('properties->attributes'),
                        false: fn ($q) => $q->whereNull('properties->attributes'),
                        blank: fn ($q) => $q
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detail')->modalHeading('Detail Aktivitas')
                    ->modalContent(fn(Activity $record) => view('filament.activity-log.show', compact('record'))),
            ])
            ->defaultSort('created_at','desc')
            ->paginationPageOptions([25,50,100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }

    public static function getModel(): string
    {
        return \Spatie\Activitylog\Models\Activity::class;
    }
}