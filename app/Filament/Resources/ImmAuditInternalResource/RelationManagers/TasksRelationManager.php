<?php

namespace App\Filament\Resources\ImmAuditInternalResource\RelationManagers;

use App\Models\ImmLampiran;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nama')
                ->label('Nama Temuan')
                ->required(),
            Forms\Components\DatePicker::make('deadline_at')
                ->label('Deadline Upload')
                ->native(false)
                ->displayFormat('d/M/Y')
                ->closeOnDateSelection()
                ->helperText('Batas waktu departemen mengunggah file')
                ->nullable(),
            Forms\Components\FileUpload::make('file')
                ->disk('private')
                ->directory('imm/audit-tasks')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama'),
                Tables\Columns\TextColumn::make('deadline_at')->date('d M Y'),
                Tables\Columns\TextColumn::make('file')->label('File')->toggleable(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
