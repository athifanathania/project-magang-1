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

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Admin';
    protected static ?string $navigationLabel = 'History Kegiatan';
    protected static ?int $navigationSort = 99;

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
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->subject_type || !$record->subject_id) return '-';
                        return class_basename($record->subject_type) . '#' . $record->subject_id;
                    })
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('properties.ip')->label('IP')->toggleable(isToggledHiddenByDefault:true),
                Tables\Columns\TextColumn::make('properties.route')->label('Route')->toggleable(isToggledHiddenByDefault:true)->wrap(),
            ])
            ->filters([
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
                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()   // pencarian di daftar options (client-side)
                    ->preload()      // muat semua options terlebih dulu
                    ->query(function (Builder $q, array $data) {
                        return $q->when($data['value'] ?? null, fn ($qq, $id) => $qq->where('causer_id', $id));
                    }),
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Objek')
                    ->options(fn () =>
                        Activity::query()
                            ->whereNotNull('subject_type')
                            ->distinct()
                            ->pluck('subject_type', 'subject_type')
                            ->all()
                    ),
                Tables\Filters\Filter::make('created_between')
                    ->form([
                        Forms\Components\DateTimePicker::make('from')->label('Dari'),
                        Forms\Components\DateTimePicker::make('to')->label('Sampai'),
                    ])
                    ->query(fn(Builder $q, array $data) =>
                        $q->when($data['from'] ?? null, fn($qq,$from) => $qq->where('created_at','>=',$from))
                          ->when($data['to'] ?? null, fn($qq,$to) => $qq->where('created_at','<=',$to))
                    ),
                Tables\Filters\TernaryFilter::make('with_diff')
                    ->label('Hanya yg ada perubahan')
                    ->queries(
                        true: fn($q)=>$q->whereNotNull('properties->attributes'),
                        false: fn($q)=>$q->whereNull('properties->attributes'),
                        blank: fn($q)=>$q
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
