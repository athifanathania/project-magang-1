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
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TagsColumn::make('roles.name')
                    ->label('Roles'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->check() && auth()->user()->hasRole('Admin')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->check() && auth()->user()->hasRole('Admin')),
            ])
            ->bulkActions([]); // tidak ada bulk action untuk aman
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('password')
                ->label('Password (kosongkan jika tidak ganti)')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $context) => $context === 'create'),

            // Hanya Admin yang bisa atur role
            Forms\Components\Select::make('roles')
                ->label('Role')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->searchable()
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
