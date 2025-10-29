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

    /** NAV: hanya tampil untuk Admin */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    /** Permission list records (Filament v3) */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')->dateTime('d/m/Y H:i')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('causer.name')->label('User')->toggleable()->searchable(),
                Tables\Columns\BadgeColumn::make('event')->label('Aksi')
                    ->colors([
                        'primary' => ['view'],
                        'success' => ['login','create','version_add','version_replace','version_reopen'],
                        'warning' => ['update','version_desc_update'],
                        'danger'  => ['delete','version_delete','logout'],
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')->label('Deskripsi')->wrap()->limit(80)->searchable(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Objek')
                    ->formatStateUsing(function ($state, \Spatie\Activitylog\Models\Activity $record) {
                        $label = null;
                        try {
                            $label = $record->getExtraProperty('object_label'); 
                        } catch (\Throwable $e) {
                        }

                        if (filled($label)) return $label;

                        if (!$record->subject_type || !$record->subject_id) return '-';
                        return class_basename($record->subject_type) . '#' . $record->subject_id;
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('properties.ip')->label('IP')->toggleable(isToggledHiddenByDefault:true),
                Tables\Columns\TextColumn::make('properties.route')->label('Route')->toggleable(isToggledHiddenByDefault:true)->wrap(),
                Tables\Columns\TextColumn::make('properties->object_label')
                    ->label('Objek (search)')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
            ])
            ->filters([
                // 1) Periode cepat
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
                    })
                    ->indicateUsing(fn (array $data) => [
                        'today'      => 'Hari ini',
                        'yesterday'  => 'Kemarin',
                        'last7'      => '7 hari terakhir',
                        'this_month' => 'Bulan ini',
                    ][$data['value'] ?? ''] ?? null),

                // 2) Event (default by column)
                Tables\Filters\SelectFilter::make('event')->options([
                    'login'               => 'login',
                    'logout'              => 'logout',
                    'view'                => 'view',
                    'create'              => 'create',
                    'update'              => 'update',
                    'delete'              => 'delete',
                    'version_add'         => 'version_add',
                    'version_replace'     => 'version_replace',
                    'version_reopen'      => 'version_reopen',
                    'version_delete'      => 'version_delete',
                    'version_desc_update' => 'version_desc_update',
                ]),

                // 3) User (stabil – pakai Filter + Select form)
                Tables\Filters\Filter::make('by_user')
                    ->label('User')
                    ->form([
                        Forms\Components\Select::make('id')
                            ->label('User')
                            ->options(fn () => \App\Models\User::query()
                                ->orderBy('name')->pluck('name', 'id')->all()
                            )
                            ->preload() // load semua opsi
                            // ->searchable()  // HAPUS baris ini
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
                        $name = \App\Models\User::whereKey($id)->value('name');
                        return $name ? "User: {$name}" : null;
                    }),

                // 4a) Objek by kelas model (subject_type)
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Objek (tipe)')
                    ->options(fn () => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($full) => [$full => class_basename($full)])
                        ->sort()
                        ->all()
                    ),

                // 5) Rentang waktu custom
                Tables\Filters\Filter::make('created_between')
                    ->label('Rentang Waktu')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari')
                            ->native(false)              
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        Forms\Components\DatePicker::make('to')
                            ->label('Sampai')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $from = filled($data['from'] ?? null) ? \Illuminate\Support\Carbon::parse($data['from'])->startOfDay() : null;
                        $to   = filled($data['to']   ?? null) ? \Illuminate\Support\Carbon::parse($data['to'])->endOfDay()   : null;

                        if ($from) $query->where('created_at', '>=', $from);
                        if ($to)   $query->where('created_at', '<=', $to);

                        return $query;
                    })
                    ->indicateUsing(function (array $data) {
                        $fmt = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y');
                        $from = $data['from'] ?? null;
                        $to   = $data['to']   ?? null;

                        if ($from && $to) return 'Dari ' . $fmt($from) . ' s/d ' . $fmt($to);
                        if ($from)         return 'Dari ' . $fmt($from);
                        if ($to)           return 'Sampai ' . $fmt($to);
                        return null;
                    }),

                // 6) Hanya yang ada perubahan
                Tables\Filters\TernaryFilter::make('with_diff')
                    ->label('Hanya yg ada perubahan')
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
