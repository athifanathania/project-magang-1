<?php

namespace App\Filament\Widgets;

use App\Models\Regular;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RegularDocumentTable extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Monitoring Dokumen Regular';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Regular::query()
                    ->withCount('lampirans')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('index')
                    ->label('No')
                    ->rowIndex(),

                Tables\Columns\TextColumn::make('cust_name') 
                    ->label('Customer')
                    ->searchable() 
                    ->sortable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('model')
                    ->label('Model')
                    ->searchable() 
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('kode_berkas')
                    ->label('Part No')
                    ->searchable() 
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('nama')
                    ->label('Part Name')
                    ->wrap()
                    ->searchable() 
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('lampirans_count')
                    ->label('Jml Lampiran')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state == 0 => 'danger',
                        $state < 3  => 'warning',
                        default     => 'success',
                    })
                    ->alignCenter()
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(5);
    }
}