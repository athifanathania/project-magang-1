<?php
// app/Filament/Resources/UserResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-m-users';
    protected static ?string $navigationLabel = 'Users';
    protected static ?int $navigationSort = 98;
    protected static ?string $navigationGroup = 'Admin';

    /** Sidebar “Users” hanya muncul untuk Admin */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin');
    }

    /** Halaman index resource ini hanya boleh diakses Admin */
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin');
    }

    /** Create juga khusus Admin */
    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                return $query
                    ->orderBy('department', 'asc') 
                    ->orderBy('name', 'asc');      
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('email')->searchable()->label('Email Perusahaan'),

                Tables\Columns\TextColumn::make('department')
                    ->label('Departemen')
                    ->sortable()
                    ->toggleable(), // bisa disembunyikan dari header

                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Non-Active')
                    ->colors([
                        'success' => fn ($state) => $state === true,
                        'danger'  => fn ($state) => $state === false,
                    ])
                    ->icon(fn ($state) =>   $state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'),

                Tables\Columns\TagsColumn::make('roles.name')->label('Roles'),
            ])
            ->filters([
                // Filter status
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Hanya Active?')
                    ->boolean(),

                // Filter departemen (ambil nilai unik dari DB)
                Tables\Filters\SelectFilter::make('department')
                    ->label('Departemen')
                    ->options(fn () => \App\Models\User::query()
                        ->whereNotNull('department')
                        ->distinct()
                        ->orderBy('department')
                        ->pluck('department','department')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->check() && auth()->user()->hasRole('Admin')),

                // Quick toggle Active/Non-Active untuk Admin
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (User $record) => $record->is_active ? 'Non-Active' : 'Set Active')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->check() && auth()->user()->hasRole('Admin'))
                    ->action(function (User $record) {
                        $record->update(['is_active' => !$record->is_active]);
                    })
                    ->color(fn (User $u) => $u->is_active ? 'danger' : 'success')
                    ->icon(fn (User $u) => $u->is_active ? 'heroicon-m-pause-circle' : 'heroicon-m-play-circle'),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->check() && auth()->user()->hasRole('Admin')),
            ])
            ->bulkActions([]); // tetap kosong untuk aman
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nama')->required()->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->label('Email Perusahaan')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->rule('ends_with:@indomatsumoto.com') 
                ->helperText('Wajib menggunakan email perusahaan (@indomatsumoto.com)')
                ->validationMessages([
                    'ends_with' => 'Email harus menggunakan domain @indomatsumoto.com',
                ]),

            Forms\Components\Select::make('department')
                ->label('Departemen')
                ->options([
                    'QC' => 'QC',
                    'PPC' => 'PPC',
                    'Produksi' => 'Produksi',
                    'Audit Internal' => 'Audit Internal',
                    'HRD' => 'HRD',
                    'Purchasing' => 'Purchasing',
                    'Marketing' => 'Marketing',
                    'Maintenance' => 'Maintenance',
                    'Engineering' => 'Engineering',
                    'Finance' => 'Finance',
                    'IT' => 'IT',
                ])
                ->searchable()
                ->placeholder('Pilih Departemen'),

            Forms\Components\Toggle::make('is_active')
                ->label('User Active')
                ->default(true)
                ->inline(false)
                ->visible(fn () => auth()->check() && auth()->user()->hasRole('Admin')),

            Forms\Components\TextInput::make('password')
                ->label('Password (kosongkan jika tidak ganti)')
                ->password()->revealable()
                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $context) => $context === 'create'),

            Forms\Components\Select::make('roles')
                ->label('Role')->relationship('roles','name')
                ->multiple()->preload()->searchable()
                ->visible(fn () => auth()->check() && auth()->user()->hasRole('Admin')),
        ]);
    }


    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
