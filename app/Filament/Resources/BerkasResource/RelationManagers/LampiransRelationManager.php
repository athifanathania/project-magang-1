<?php

namespace App\Filament\Resources\BerkasResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LampiransRelationManager extends RelationManager
{
    protected static string $relationship = 'lampirans';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(255),

                Forms\Components\FileUpload::make('file')
                    ->disk('public')
                    ->directory('lampiran')
                    ->downloadable()
                    ->previewable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama'),
                Tables\Columns\TextColumn::make('file')
                    ->url(fn ($record) => $record->file ? asset('storage/' . $record->file) : null, shouldOpenInNewTab: true)
                    ->formatStateUsing(fn ($state) => $state ? '📂 Lihat File' : '-'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}