<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImmManualMutuResource\Pages;
use App\Models\ImmManualMutu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\View as ViewField;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Filters\Filter;

class ImmManualMutuResource extends Resource
{
    protected static ?string $model = ImmManualMutu::class;

    protected static ?string $navigationGroup = 'Dokumen IMM';
    protected static ?string $navigationLabel = 'Manual Mutu';
    protected static ?string $modelLabel      = 'Manual Mutu';
    protected static ?string $pluralModelLabel= 'Manual Mutu';
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        $readonly = fn (string $ctx = null) => $ctx === 'view';

        return $form->schema([
            Section::make()->schema([
                TextInput::make('no')->label('No')->required()->disabled($readonly),
                TextInput::make('nama_dokumen')->label('Nama Dokumen')->required()->disabled($readonly),

                // file aktif; upload file BARU = otomatis versi baru (logika di trait)
                FileUpload::make('file')
                    ->label('File')
                    ->disk('private')
                    ->directory('imm/manual_mutu')
                    ->preserveFilenames()
                    ->rules(['nullable','file'])
                    ->downloadable(false)
                    ->openable(false)
                    ->disabled($readonly),

                // tidak ditampilkan di tabel list, tapi berguna untuk filter
                TagsInput::make('keywords')->label('Kata Kunci')->separator(',')->reorderable()->disabled($readonly),
            ])->columns(2),

            // Khusus modal view: tampilkan riwayat
            Section::make('Riwayat Dokumen')
                ->visible(fn (string $ctx) => $ctx === 'view')
                ->schema([
                    ViewField::make('imm_history')->view('tables.rows.imm-history')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tbl = (new ImmManualMutu)->getTable();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no')->label('No')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('nama_dokumen')
                    ->label('Nama Dokumen')
                    ->wrap()
                    ->extraCellAttributes(['class'=>'max-w-[28rem] whitespace-normal break-words'])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('file')
                    ->label('File')
                    ->state(fn ($r) => $r->file ? '📂' : '—')
                    ->url(fn ($r) => $r->file ? route('media.imm', ['group'=>'manual-mutu','id'=>$r->getKey()]) : null, shouldOpenInNewTab: true)
                    ->color(fn ($r) => $r->file ? 'primary' : null)
                    ->extraAttributes(['class'=>'text-blue-600 hover:underline']),
            ])

            // === FILTER mirip Berkas (keyword chips, mode ANY/ALL) ===
            ->filters([
                Filter::make('q')
                    ->label('Cari')
                    ->form([
                        \Filament\Forms\Components\TagsInput::make('terms')
                            ->label('Kata kunci')
                            ->placeholder('Ketik lalu Enter')
                            ->separator(',')
                            ->reorderable(),
                        \Filament\Forms\Components\Toggle::make('all')
                            ->label('Cocokkan semua keyword (mode ALL)')
                            ->inline(false),
                    ])
                    ->query(function (Builder $query, array $data) use ($tbl) {
                        $terms = collect($data['terms'] ?? [])
                            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                            ->map(fn ($t) => trim($t))
                            ->unique()
                            ->values()
                            ->all();

                        if (empty($terms)) return;

                        $modeAll = (bool) ($data['all'] ?? false);

                        $one = function (Builder $q, string $term) use ($tbl) {
                            $likeLower = '%' . mb_strtolower($term) . '%';
                            $q->whereRaw("LOWER({$tbl}.no) LIKE ?", [$likeLower])
                              ->orWhereRaw("LOWER({$tbl}.nama_dokumen) LIKE ?", [$likeLower])
                              ->orWhereRaw("LOWER({$tbl}.file) LIKE ?", [$likeLower])
                              ->orWhereRaw("LOWER(CAST({$tbl}.keywords AS CHAR)) LIKE ?", [$likeLower]);
                        };

                        $query->where(function (Builder $outer) use ($terms, $modeAll, $one) {
                            if ($modeAll) {
                                foreach ($terms as $t) $outer->where(fn ($qq) => $one($qq, $t));
                            } else {
                                $outer->where(fn ($qq) => collect($terms)->each(fn ($t) => $qq->orWhere(fn ($q) => $one($q,$t))));
                            }
                        });
                    })
                    ->indicateUsing(fn (array $data) =>
                        ($tags = collect($data['terms'] ?? [])->filter()->implode(', ')) ? "Cari: $tags" : null
                    ),
            ])

            ->actions([
                ViewAction::make()
                    ->label('')
                    ->icon('heroicon-m-eye')
                    ->tooltip('Lihat (riwayat)'),

                EditAction::make()
                    ->label('')
                    ->icon('heroicon-m-pencil')
                    ->tooltip('Edit')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),

                DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-m-trash')
                    ->tooltip('Hapus')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListImmManualMutus::route('/'),
            'create' => Pages\CreateImmManualMutu::route('/create'),
            'edit'   => Pages\EditImmManualMutu::route('/{record}/edit'),
        ];
    }
}
