<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImmInstruksiStandarResource\Pages;
use App\Models\ImmInstruksiStandar;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TagsInput;

class ImmInstruksiStandarResource extends Resource
{
    protected static ?string $model = ImmInstruksiStandar::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Dokumen IMM';
    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'Intruksi Kerja & Standar';
    protected static ?string $pluralModelLabel = 'Intruksi Kerja & Standar';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('no')->label('No')->required()->maxLength(100),
            TextInput::make('nama_dokumen')->label('Nama Dokumen')->required()->maxLength(255),
            TagsInput::make('keywords')->label('Kata Kunci')->separator(',')->reorderable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('no')->label('No')->sortable()->searchable()->extraCellAttributes(['class'=>'whitespace-nowrap']),
            TextColumn::make('nama_dokumen')->label('Nama Dokumen')->searchable()->wrap(),
            TextColumn::make('keywords')->label('Kata Kunci')->formatStateUsing(fn($v)=>collect($v??[])->join(', '))->toggleable(true),
            TextColumn::make('lihat_file')
                ->label('Lihat File')
                ->state(fn (ImmInstruksiStandar $r) => $r->file ? '📄' : '—')
                ->url(fn (ImmInstruksiStandar $r) => $r->file ? route('media.imm.file', ['type'=>'instruksi-standar','id'=>$r->getKey()]) : null, true)
                ->extraAttributes(['class'=>'text-lg']),
        ])->actions([
            ViewAction::make()
                ->label('')->icon('heroicon-m-eye')->tooltip('Lihat')
                ->modalHeading('Detail Intruksi Kerja & Standar')->modalWidth('7xl')
                ->modalContent(fn(ImmInstruksiStandar $record)=>view('imm.partials.history', ['record'=>$record])),
            EditAction::make()->label('')->icon('heroicon-m-pencil')->tooltip('Edit')
                ->visible(fn()=>auth()->user()?->hasAnyRole(['Admin','Editor'])??false),
            DeleteAction::make()->label('')->icon('heroicon-m-trash')->tooltip('Hapus')
                ->visible(fn()=>auth()->user()?->hasAnyRole(['Admin','Editor'])??false),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make()->visible(fn()=>auth()->user()?->hasAnyRole(['Admin','Editor'])??false),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListImmInstruksiStandars::route('/'),
            'create' => Pages\CreateImmInstruksiStandar::route('/create'),
            'edit'   => Pages\EditImmInstruksiStandar::route('/{record}/edit'),
        ];
    }
}
