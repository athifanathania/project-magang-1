<?php

namespace App\Filament\Widgets;

use App\Models\ImmProsedur;
use App\Models\ImmLampiran;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ProsedurDocumentTable extends BaseWidget
{
    protected static ?int $sort = 6; 
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Monitoring Dokumen Prosedur';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // UBAH JADI OLDEST
                ImmProsedur::query()->oldest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('index')
                    ->label('No')
                    ->rowIndex(),

                Tables\Columns\TextColumn::make('nama_dokumen')
                    ->label('Nama Dokumen')
                    ->wrap()
                    ->limit(80)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('keywords')
                    ->label('Kata Kunci')
                    ->formatStateUsing(function ($state) {
                        if (blank($state)) return '-';
                        return is_array($state) ? implode(', ', $state) : $state;
                    })
                    ->limit(50)
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('total_lampiran')
                    ->label('Jml Lampiran')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        return ImmLampiran::query()
                            ->where('documentable_id', $record->id)
                            ->whereIn('documentable_type', ['App\Models\ImmProsedur', 'ImmProsedur', 'prosedur'])
                            ->whereNull('parent_id') 
                            ->count();
                    })
                    ->formatStateUsing(function ($state, $record) {
                        $subCount = ImmLampiran::query()
                            ->where('documentable_id', $record->id)
                            ->whereIn('documentable_type', ['App\Models\ImmProsedur', 'ImmProsedur', 'prosedur'])
                            ->whereNotNull('parent_id') 
                            ->count();

                        if ($subCount > 0) {
                            return "$state (+ $subCount Sub)";
                        }
                        return $state;
                    })
                    ->color(fn ($state) => match (true) {
                        $state == 0 => 'danger',
                        $state < 3  => 'warning',
                        default     => 'success',
                    })
                    ->alignCenter(),
            ])
            ->defaultPaginationPageOption(5);
    }
}