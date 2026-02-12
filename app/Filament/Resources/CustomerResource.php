<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight; 
use Filament\Infolists\Components\TextEntry;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?int    $navigationSort  = 5;
    protected static ?string $navigationGroup = 'Dokumen Eksternal';
    protected static ?string $navigationLabel = 'Customer Template'; 
    protected static ?string $modelLabel = 'Customer Template';      
    protected static ?string $pluralModelLabel = 'Customer Template';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nama Customer')
                    ->required()
                    ->columnSpanFull(),

                // INPUT LIST DOKUMEN DALAM 1 TABEL
                Repeater::make('document_templates')
                    ->label('Template Dokumen Pelengkap')
                    ->schema([
                        TextInput::make('name') // Key-nya 'name'
                            ->label('Nama Dokumen')
                            // ->required()
                            ,
                    ])
                    ->columnSpanFull()
                    ->grid(2) // Tampilan 2 kolom biar rapi
                    ->addActionLabel('Tambah Template Dokumen'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Menampilkan Nama Customer
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Customer')
                    ->searchable()
                    ->sortable(),

                // 2. (Opsional) Menampilkan jumlah template dokumen yang dimiliki
                Tables\Columns\TextColumn::make('document_templates')
                    ->label('Jumlah Template')
                    ->formatStateUsing(function ($state, $record) {
                        $templates = $record->document_templates;

                        if (empty($templates) || !is_array($templates)) {
                            return '0 Dokumen Template';
                        }

                        $count = collect($templates)
                            ->filter(fn ($item) => !empty($item['name'])) 
                            ->count();

                        return $count . ' Dokumen Template';
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Lihat Detail'),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // BAGIAN 1: INFORMASI CUSTOMER
                Infolists\Components\Section::make('Informasi Customer')
                    ->icon('heroicon-m-user') // Ikon user
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Nama Customer')
                            ->weight(FontWeight::Bold) // Teks jadi tebal
                            ->size(TextEntry\TextEntrySize::Large) // Teks lebih besar
                            ->icon('heroicon-m-identification') // Ikon kecil di samping teks
                            ->copyable(), // Bisa di-copy kalau diklik
                    ])->columns(2),

                // BAGIAN 2: DAFTAR DOKUMEN
                Infolists\Components\Section::make('Daftar Dokumen')
                    ->description('List template dokumen yang diperlukan')
                    ->icon('heroicon-m-document-duplicate') // Ikon dokumen tumpuk
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('document_templates')
                            ->label('') 
                            ->placeholder('Belum ada dokumen yang diinput') 
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Dokumen')
                                    ->icon('heroicon-m-document')
                                    ->iconColor('primary')
                                    ->weight(FontWeight::Bold),
                            ])
                            ->grid(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
