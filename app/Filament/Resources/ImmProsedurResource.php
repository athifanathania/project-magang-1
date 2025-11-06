<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImmProsedurResource\Pages;
use App\Models\ImmProsedur;
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
use Illuminate\Support\Facades\Gate;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use App\Filament\Support\RowClickViewForNonEditors;
use App\Filament\Support\FileCell;

class ImmProsedurResource extends Resource
{
    protected static ?string $model = ImmProsedur::class;
    protected static ?string $navigationGroup = 'Dokumen Internal';
    protected static ?string $navigationLabel = '2. Prosedur';
    protected static ?string $modelLabel      = '2. Prosedur';
    protected static ?string $pluralModelLabel= 'Prosedur';
    protected static ?int    $navigationSort  = 2; 
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    
    use RowClickViewForNonEditors, FileCell;
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('nama_dokumen')
                    ->label('Nama Dokumen')->required()->disabledOn('view'),

                Forms\Components\FileUpload::make('file')
                    ->label('File')->disk('private')->directory('imm/prosedur')
                    ->preserveFilenames()->rules(['nullable','file'])
                    ->downloadable(false)->openable(false)->disabledOn('view')
                    ->saveUploadedFileUsing(function ($file, $record) {
                        if ($record) {
                            $ver = $record->addVersionFromUpload($file);
                            return $ver['file_path'] ?? $record->file;
                        }
                        return $file->store('imm/tmp', 'private');
                    })
                    ->hintAction(
                        FormAction::make('openFile')
                            ->label('Buka file')
                            ->url(
                                fn ($record) => ($record && $record->file)
                                    ? route('media.imm.file', ['type' => 'prosedur', 'id' => $record->getKey()])
                                    : null,
                                shouldOpenInNewTab: true
                            )
                            ->visible(fn ($record) =>
                                filled($record?->file)
                                && (auth()->user()?->hasAnyRole(['Admin','Editor','Staff']) ?? false)
                            )
                    ),

                Forms\Components\TagsInput::make('keywords')
                    ->label('Kata Kunci')->separator(',')->reorderable()->disabledOn('view'),
                FileUpload::make('file_src')
                    ->label('File Asli (Admin saja)')
                    ->disk('private')
                    ->directory('imm/prosedur/_source')
                    ->preserveFilenames()
                    ->rules(['nullable','file'])
                    ->previewable(true)
                    ->downloadable(false)
                    ->openable(false)
                    ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false)
                    ->helperText('Hanya Admin yang dapat mengganti file asli'),

            ])->columns(2),

            Forms\Components\Section::make('Riwayat Dokumen Prosedur')
                ->visibleOn('view')
                ->schema([
                    Forms\Components\View::make('imm_history')
                        ->view('tables.rows.imm-history')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tbl = (new \App\Models\ImmProsedur)->getTable();
        $childTbl = (new \App\Models\ImmLampiran)->getTable();

        return static::applyRowClickPolicy($table)
            // ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('nama_dokumen')
                    ->label('Nama Dokumen')->wrap()
                    ->extraCellAttributes(['class'=>'max-w-[28rem] whitespace-normal break-words'])
                    ->sortable(),

                // ⬇️ Tambahan kolom KATA KUNCI
                Tables\Columns\ViewColumn::make('keywords_view')
                    ->label('Kata Kunci')
                    ->state(function (ImmProsedur $record) {
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
                    ->formatStateUsing(fn ($state) => $state ? '📂' : '-')
                    ->url(fn ($record) =>
                        ($record->file && (auth()->user()?->hasAnyRole(['Admin','Editor','Staff']) ?? false))
                            ? route('media.imm.file', ['type' => 'prosedur', 'id' => $record->getKey()])
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
                    ->query(function (Builder $query, array $data) use ($tbl, $childTbl) {
                        $terms = collect($data['terms'] ?? [])
                            ->filter(fn($t)=>is_string($t)&&trim($t)!=='')
                            ->map(fn($t)=>trim($t))->unique()->values()->all();
                        if (empty($terms)) return;

                        $modeAll = (bool) ($data['all'] ?? false);

                        $one = function (Builder $q2, string $term) use ($tbl, $childTbl) {
                            $like = '%'.mb_strtolower($term).'%';
                            $q2->where(function (Builder $g) use ($like, $tbl, $childTbl) {
                                $g->whereRaw("LOWER({$tbl}.nama_dokumen) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.file) LIKE ?", [$like])
                                ->orWhereRaw("LOWER(CAST({$tbl}.keywords AS CHAR)) LIKE ?", [$like])
                                ->orWhere(function (Builder $q) use ($like, $childTbl) {
                                    $q->whereHas('lampirans', function (Builder $l) use ($like, $childTbl) {
                                        $l->where(function (Builder $lx) use ($like, $childTbl) {
                                            $lx->whereRaw("LOWER({$childTbl}.nama) LIKE ?",  [$like])
                                                ->orWhereRaw("LOWER({$childTbl}.file) LIKE ?",  [$like])
                                                ->orWhereRaw("LOWER(CAST({$childTbl}.keywords AS CHAR)) LIKE ?", [$like]);
                                        });
                                    });
                                });
                            });
                        };

                        $query->where(function (Builder $outer) use ($terms, $modeAll, $one) {
                            if ($modeAll) foreach ($terms as $t) $outer->where(fn($qq)=>$one($qq,$t));
                            else $outer->where(function ($subOr) use ($terms, $one) {
                                foreach ($terms as $t) $subOr->orWhere(fn($qq)=>$one($qq,$t));
                            });
                        });
                    })
                    ->indicateUsing(fn (array $data) =>
                        ($tags = collect($data['terms'] ?? [])->filter()->implode(', ')) ? "Cari: {$tags}" : null
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('')->icon('heroicon-m-eye')->tooltip('Lihat (riwayat)')->modalWidth('7xl'),
                Tables\Actions\EditAction::make()->label('')->icon('heroicon-m-pencil')->tooltip('Edit')
                    ->visible(fn()=>auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
                Tables\Actions\DeleteAction::make()->label('')->icon('heroicon-m-trash')->tooltip('Hapus')
                    ->visible(fn()=>auth()->user()?->hasRole('Admin') ?? false),
                Action::make('downloadSource')
                    ->label('')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => route('download.source', [
                        'type' => 'imm-prosedur',
                        'id'   => $record->getKey(),
                    ]))
                    ->openUrlInNewTab()
                    ->visible(fn () => Gate::allows('download-source'))
                    ->disabled(fn ($record) => blank($record->file_src))
                    ->tooltip(fn ($record) => blank($record->file_src) ? 'File asli belum diunggah' : null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListImmProsedurs::route('/'),
            'create' => Pages\CreateImmProsedur::route('/create'),
            'edit'   => Pages\EditImmProsedur::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }


}
