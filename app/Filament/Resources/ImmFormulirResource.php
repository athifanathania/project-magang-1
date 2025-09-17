<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImmFormulirResource\Pages;
use App\Models\ImmFormulir;
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

class ImmFormulirResource extends Resource
{
    protected static ?string $model = ImmFormulir::class;

    protected static ?string $navigationGroup = 'Dokumen IMM';
    protected static ?string $navigationLabel = '4. Formulir';
    protected static ?string $modelLabel      = '4. Formulir';
    protected static ?string $pluralModelLabel= 'Formulir';
    protected static ?int    $navigationSort  = 4; 
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('nama_dokumen')
                    ->label('Nama Dokumen')->required()->disabledOn('view'),

                Forms\Components\FileUpload::make('file')
                    ->label('File')->disk('private')->directory('imm/formulir')
                    ->preserveFilenames()->rules(['nullable','file'])
                    ->downloadable(false)->openable(false)->disabledOn('view')
                    ->saveUploadedFileUsing(function ($file, $record) {
                        if ($record) {
                            $ver = $record->addVersionFromUpload($file);
                            return $ver['file_path'] ?? $record->file;
                        }
                        return $file->store('imm/tmp', 'private');
                    }),

                Forms\Components\TagsInput::make('keywords')
                    ->label('Kata Kunci')->separator(',')->reorderable()->disabledOn('view'),
            ])->columns(2),

            Forms\Components\Section::make('Riwayat Dokumen')
                ->visibleOn('view')
                ->schema([
                    Forms\Components\View::make('imm_history')
                        ->view('tables.rows.imm-history')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tbl = (new \App\Models\ImmFormulir)->getTable();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_dokumen')
                    ->label('Nama Dokumen')->wrap()
                    ->extraCellAttributes(['class'=>'max-w-[28rem] whitespace-normal break-words'])
                    ->sortable()->searchable(),

                // ⬇️ Tambahan kolom KATA KUNCI
                Tables\Columns\ViewColumn::make('keywords_view')
                    ->label('Kata Kunci')
                    ->state(function (ImmFormulir $record) {
                        // Ambil nilai mentah dari DB (bisa json string / array / csv)
                        $raw = $record->getRawOriginal('keywords');

                        $toArray = function ($v) {
                            if (blank($v)) return [];
                            if (is_string($v)) {
                                $j = json_decode($v, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($j)) return $j;
                                return preg_split('/\s*,\s*/u', $v, -1, PREG_SPLIT_NO_EMPTY); // CSV
                            }
                            if (is_array($v)) return $v;
                            return (array) $v;
                        };

                        $arr = $toArray($raw);

                        // Pecah juga item berbentuk CSV di dalam array
                        return collect($arr)
                            ->flatMap(fn($item) =>
                                is_array($item)
                                    ? $item
                                    : preg_split('/\s*,\s*/u', trim((string)$item), -1, PREG_SPLIT_NO_EMPTY)
                            )
                            ->map(fn($s) => trim((string)$s, " \t\n\r\0\x0B\"'"))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();
                    })
                    ->view('tables.columns.keywords-grid') // ⬅️ chip view yang sama dengan halaman Berkas
                    ->extraCellAttributes(['class' => 'max-w-[24rem] whitespace-normal break-words'])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('file')
                    ->label('File')
                    ->formatStateUsing(fn ($state) => $state ? '📂' : '—')
                    ->url(fn ($record) =>
                        ($record->file && (auth()->user()?->hasAnyRole(['Admin','Editor','Staff']) ?? false))
                            ? route('media.imm.file', ['type' => 'formulir', 'id' => $record->getKey()])
                            : null,
                        shouldOpenInNewTab: true
                    )
                    ->color(fn ($record) => $record->file ? 'primary' : null)
                    ->extraAttributes(['class'=>'text-blue-600 hover:underline']),
            ])
            ->filters([
                Tables\Filters\Filter::make('q')
                    ->label('Cari')
                    ->form([
                        Forms\Components\TagsInput::make('terms')->label('Kata kunci')->separator(',')->reorderable(),
                        Forms\Components\Toggle::make('all')->label('Cocokkan semua keyword (mode ALL)')->inline(false),
                    ])
                    ->query(function (Builder $query, array $data) use ($tbl) {
                        $terms = collect($data['terms'] ?? [])
                            ->filter(fn($t)=>is_string($t)&&trim($t)!=='')
                            ->map(fn($t)=>trim($t))->unique()->values()->all();
                        if (empty($terms)) return;

                        $modeAll = (bool) ($data['all'] ?? false);
                        $one = function (Builder $q, string $term) use ($tbl) {
                            $like = '%' . mb_strtolower($term) . '%';
                            $q->whereRaw("LOWER({$tbl}.nama_dokumen) LIKE ?", [$like])
                            ->orWhereRaw("LOWER({$tbl}.file) LIKE ?", [$like])
                            ->orWhereRaw("LOWER(CAST({$tbl}.keywords AS CHAR)) LIKE ?", [$like]);
                        };

                        $query->where(function (Builder $outer) use ($terms, $modeAll, $one) {
                            if ($modeAll) foreach ($terms as $t) $outer->where(fn($qq)=>$one($qq,$t));
                            else $outer->where(fn($qq)=>collect($terms)->each(fn($t)=>$qq->orWhere(fn($q)=>$one($q,$t))));
                        });
                    })
                    ->indicateUsing(fn (array $data) =>
                        ($tags = collect($data['terms'] ?? [])->filter()->implode(', ')) ? "Cari: $tags" : null
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('')->icon('heroicon-m-eye')->tooltip('Lihat (riwayat)'),
                Tables\Actions\EditAction::make()->label('')->icon('heroicon-m-pencil')->tooltip('Edit')
                    ->visible(fn()=>auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
                Tables\Actions\DeleteAction::make()->label('')->icon('heroicon-m-trash')->tooltip('Hapus')
                    ->visible(fn()=>auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListImmFormulirs::route('/'),
            'create' => Pages\CreateImmFormulir::route('/create'),
            'edit'   => Pages\EditImmFormulir::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false;
    }

}
