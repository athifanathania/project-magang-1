<?php

namespace App\Filament\Widgets;

use App\Models\Berkas;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class EventDocumentTable extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Monitoring Dokumen Event';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Berkas::query()
                    ->withCount('lampirans')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('index')
                    ->label('No')
                    ->rowIndex(),

                Tables\Columns\TextColumn::make('cust_name')
                    ->label('Cust Name')
                    ->searchable() 
                    ->sortable()
                    ->limit(16),

                Tables\Columns\TextColumn::make('model')
                    ->label('Model')
                    ->searchable() 
                    ->sortable(),

                Tables\Columns\TextColumn::make('kode_berkas')
                    ->label('Part No')
                    ->searchable() 
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('nama')
                    ->label('Part Name')
                    ->wrap()
                    ->limit(50)
                    ->searchable(), 

                Tables\Columns\TextColumn::make('detail')
                    ->label('Detail')
                    ->limit(30)
                    ->searchable() 
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('lampirans_count')
                    ->label('Jml Lampiran')
                    ->badge()
                    ->color(fn (string $state): string => $state > 0 ? 'success' : 'danger')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(5);
    }
}