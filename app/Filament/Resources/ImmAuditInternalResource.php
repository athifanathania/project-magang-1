<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImmAuditInternalResource\Pages;
use App\Models\ImmAuditInternal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Facades\Filament;

class ImmAuditInternalResource extends Resource
{
    protected static ?string $model = ImmAuditInternal::class;

    protected static ?string $navigationGroup = 'Dokumen IMM';
    protected static ?string $navigationLabel = 'Dokumen Audit Internal';
    protected static ?string $pluralModelLabel = 'Dokumen Audit Internal';
    protected static ?string $modelLabel = 'Dokumen Audit Internal';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?int $navigationSort = 50; // atur sesuai kebutuhan

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('departemen')
                ->label('Nama Departemen')
                ->datalist([
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
                // ->searchable()
                ->required()
                ->placeholder('Pilih Departemen'),

            Select::make('semester')
                ->label('Semester')
                ->options([1 => '1', 2 => '2'])
                ->required(),

            TextInput::make('tahun')
                ->label('Tahun Periode')
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('departemen')->label('Nama Departemen')->sortable()->searchable(),
                TextColumn::make('semester')->label('Semester')->sortable(),
                TextColumn::make('tahun')->label('Tahun')->sortable(),
            ])
            ->actions([
                // Panel TASK (Lampiran) – pakai view baru
                Action::make('task')
                    ->label('Temuan Audit')
                    ->icon('heroicon-m-queue-list')
                    ->modalHeading('Temuan Audit')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('7xl')
                    ->modalContent(fn (ImmAuditInternal $record) =>
                        view('tables.rows.audit-task-panel-plain', ['record' => $record])
                    ),

                ViewAction::make()->label('')->icon('heroicon-m-eye'),
                EditAction::make()
                    ->label('')
                    ->icon('heroicon-m-pencil')
                    ->visible(fn() => auth()->user()?->hasRole('Admin') ?? false),
                DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-m-trash')
                    ->visible(fn() => auth()->user()?->hasRole('Admin') ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListImmAuditInternals::route('/'),
            'create' => Pages\CreateImmAuditInternal::route('/create'),
            'edit'   => Pages\EditImmAuditInternal::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $panel = optional(Filament::getCurrentPanel())->getId();

        // Panel publik boleh melihat (read-only)
        if ($panel === 'public') {
            return true;
        }

        // Panel admin: tetap pakai aturan lama
        return (auth()->user()?->can('imm.view') ?? false)
            || (auth()->user()?->hasAnyRole(['Admin','Editor','Staff']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }
    public static function canEdit($record): bool
    {
        return auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false;
    }
    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

}
