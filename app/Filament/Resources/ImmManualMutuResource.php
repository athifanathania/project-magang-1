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
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Enums\RecordCheckboxPosition;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\View as ViewLayout;
use Illuminate\Support\Facades\Gate;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use App\Filament\Support\RowClickViewForNonEditors;
use App\Filament\Support\FileCell;

class ImmManualMutuResource extends Resource
{
    protected static ?string $model = ImmManualMutu::class;

    protected static ?string $navigationGroup = 'Dokumen Internal';
    protected static ?string $navigationLabel = '1. Manual Mutu';
    protected static ?string $modelLabel      = 'Manual Mutu';
    protected static ?string $pluralModelLabel= '1. Manual Mutu';
    protected static ?int    $navigationSort  = 1; 
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    
    use RowClickViewForNonEditors, FileCell;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('nama_dokumen')
                    ->label('Nama Dokumen')->required()->disabledOn('view'),

                Forms\Components\FileUpload::make('file')
                    ->label('File')->disk('private')->directory('imm/manual_mutu')
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
                                    ? route('media.imm.file', ['type' => 'manual-mutu', 'id' => $record->getKey()])
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
                    ->directory('imm/manual-mutu/_source')
                    ->preserveFilenames()
                    ->rules(['nullable','file'])
                    ->previewable(true)
                    ->downloadable(false)
                    ->openable(false)
                    ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false)
                    ->helperText('Hanya Admin yang dapat mengganti file asli'),
            ])->columns(2),

            Forms\Components\Section::make('Riwayat Dokumen Manual Mutu')
                ->visibleOn('view')
                ->schema([
                    Forms\Components\View::make('imm_history')
                        ->view('tables.rows.imm-history')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tbl = (new ImmManualMutu)->getTable();

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
                    ->state(function (ImmManualMutu $record) {
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
                            ? route('media.imm.file', ['type' => 'manual-mutu', 'id' => $record->getKey()])
                            : null,
                        shouldOpenInNewTab: true
                    )
                    ->color(fn ($record) => $record->file ? 'primary' : null)
                    ->extraAttributes(['class'=>'text-blue-600 hover:underline']),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('q')
                    ->label('Cari')
                    ->form([
                        Forms\Components\TagsInput::make('terms')
                            ->label('Kata kunci')->separator(',')->reorderable(),
                        Forms\Components\Toggle::make('all')
                            ->label('Cocokkan semua keyword (mode ALL)')->inline(false),
                    ])
                    ->query(function (Builder $query, array $data) use ($tbl): void {
                        $terms = collect($data['terms'] ?? [])
                            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                            ->map(fn ($t) => trim($t))
                            ->unique()
                            ->values()
                            ->all();

                        if (empty($terms)) return;

                        $modeAll = (bool) ($data['all'] ?? false);

                        // 💡 pakai nama tabel anak dari modelnya
                        $childTbl = (new \App\Models\ImmLampiran)->getTable();

                        $buildOne = function (Builder $q2, string $term) use ($tbl, $childTbl): void {
                            $like = '%'.mb_strtolower($term).'%';

                            $q2->where(function (Builder $g) use ($like, $tbl, $childTbl) {
                                // Induk
                                $g->whereRaw("LOWER({$tbl}.nama_dokumen) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.file) LIKE ?", [$like])
                                ->orWhereRaw("LOWER(CAST({$tbl}.keywords AS CHAR)) LIKE ?", [$like]);

                                // Relasi (dalam satu kurung or)
                                $g->orWhere(function (Builder $q) use ($like, $childTbl) {
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

                        $query->where(function (Builder $outer) use ($terms, $modeAll, $buildOne) {
                            if ($modeAll) {
                                foreach ($terms as $t) $outer->where(fn (Builder $sub) => $buildOne($sub, $t));
                            } else {
                                $outer->where(function (Builder $subOr) use ($terms, $buildOne) {
                                    foreach ($terms as $t) $subOr->orWhere(fn (Builder $sub) => $buildOne($sub, $t));
                                });
                            }
                        });
                    })
                    ->indicateUsing(function (array $data) {
                        $tags = collect($data['terms'] ?? [])->filter()->implode(', ');
                        return $tags ? "Cari: {$tags}" : null;
                    }),
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
                        'type' => 'imm-manual-mutu',
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
            'index'  => Pages\ListImmManualMutus::route('/'),
            'create' => Pages\CreateImmManualMutu::route('/create'),
            'edit'   => Pages\EditImmManualMutu::route('/{record}/edit'),
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
