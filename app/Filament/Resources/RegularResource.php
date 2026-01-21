<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegularResource\Pages;
use App\Filament\Support\FileCell;
use App\Filament\Support\RowClickViewForNonEditors;
use App\Models\Regular;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;

class RegularResource extends Resource
{
    protected static ?string $model = Regular::class;

    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Regular';
    protected static ?string $navigationGroup = 'Dokumen Eksternal';
    protected static ?int    $navigationSort  = 0;

    protected static ?string $modelLabel       = 'dokumen';
    protected static ?string $pluralModelLabel = 'Regular';

    use RowClickViewForNonEditors, FileCell;

    public static function form(Form $form): Form
    {
        // Ambil form base dari BerkasResource
        $baseForm = \App\Filament\Resources\BerkasResource::form($form);

        // Ambil komponen (v3: getComponents), lalu sesuaikan direktori/file hint untuk Regular
        $components = collect($baseForm->getComponents())->map(function ($c) {
            if ($c instanceof \Filament\Forms\Components\FileUpload) {
                if ($c->getName() === 'dokumen') {
                    // 1. Set Disk & Directory
                    $c->disk('private')->directory('regular')
                    
                    // 2. Pastikan preserveFilenames aktif
                    ->preserveFilenames()

                    // 3. TIMPA logika penyimpanan agar nama file tetap asli
                    ->saveUploadedFileUsing(function ($file, $record) {
                        // A. Jika Edit (Record sudah ada), pakai logika versioning
                        if ($record) {
                            // Pastikan trait HasBerkasVersions menangani nama file di dalamnya
                            $ver = $record->addVersionFromUpload($file);
                            return $ver['file_path'] ?? $record->dokumen;
                        }

                        // B. Jika Create (Baru), simpan manual dengan nama ASLI
                        // Gunakan storeAs, bukan store
                        return $file->storeAs(
                            'regular', // folder tujuan
                            $file->getClientOriginalName(), // nama file asli
                            'private' // disk
                        );
                    });

                    // 4. Hint Action (Tombol Buka File)
                    $c->hintAction(
                        \Filament\Forms\Components\Actions\Action::make('openFile')
                            ->label('Buka file')
                            ->url(
                                fn ($record) => ($record && $record->dokumen)
                                    ? route('media.regular', $record)
                                    : null,
                                shouldOpenInNewTab: true
                            )
                            ->visible(fn ($record) =>
                                filled($record?->dokumen)
                                && (auth()->user()?->hasAnyRole(['Admin','Editor','Staff']) ?? false)
                            )
                    );
                }

                // Thumbnail → tetap di public, tapi pisahkan folder biar rapi
                if ($c->getName() === 'thumbnail') {
                    $c->disk('public')->directory('regular/thumbnails');
                }

                // File sumber (admin) → pisahkan juga
                if ($c->getName() === 'dokumen_src') {
                    $c->disk('private')->directory('regular/_source');
                }
            }

            // Detail event untuk halaman Regular harus fix "Regular"
            if ($c instanceof \Filament\Forms\Components\TextInput && $c->getName() === 'detail') {
                $c->default('Regular')->disabled(); // biar selalu "Regular"
            }

            return $c;
        })->all();

        // Pasang kembali ke form Regular
        return $form->schema($components);
    }

    public static function table(Table $table): Table
    {
        $t = \App\Filament\Resources\BerkasResource::table($table);

        $tbl = (new \App\Models\Regular)->getTable();

        return $t
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->striped()
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Dropdown)
            ->filters([
                Filter::make('hierarchy')
                    ->label('Filter Dokumen')
                    ->form([
                        Select::make('cust_name')
                            ->label('Cust Name')
                            ->options(fn () =>
                                Regular::query()
                                    ->whereNotNull('cust_name')
                                    ->distinct()
                                    ->orderBy('cust_name')
                                    ->pluck('cust_name', 'cust_name')
                            )
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => [
                                $set('model', null),
                                $set('kode_berkas', null),
                            ]),

                        Select::make('model')
                            ->label('Model')
                            ->options(fn (Get $get) =>
                                Regular::query()
                                    ->when(
                                        $get('cust_name'),
                                        fn ($q) => $q->where('cust_name', $get('cust_name'))
                                    )
                                    ->whereNotNull('model')
                                    ->distinct()
                                    ->orderBy('model')
                                    ->pluck('model', 'model')
                            )
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) =>
                                $set('kode_berkas', null)
                            ),

                        Select::make('kode_berkas')
                            ->label('Part No')
                            ->options(fn (Get $get) =>
                                Regular::query()
                                    ->when(
                                        $get('cust_name'),
                                        fn ($q) => $q->where('cust_name', $get('cust_name'))
                                    )
                                    ->when(
                                        $get('model'),
                                        fn ($q) => $q->where('model', $get('model'))
                                    )
                                    ->whereNotNull('kode_berkas')
                                    ->distinct()
                                    ->orderBy('kode_berkas')
                                    ->pluck('kode_berkas', 'kode_berkas')
                            ),
                    ])

                    // 🔹 QUERY UTAMA (WAJIB di Filter, bukan di Select)
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when(
                                $data['cust_name'] ?? null,
                                fn ($q, $v) => $q->where('cust_name', $v)
                            )
                            ->when(
                                $data['model'] ?? null,
                                fn ($q, $v) => $q->where('model', $v)
                            )
                            ->when(
                                $data['kode_berkas'] ?? null,
                                fn ($q, $v) => $q->where('kode_berkas', $v)
                            );
                    })

                    // 🔹 Label active filter (kayak BOM)
                    ->indicateUsing(function (array $data): array {
                        return collect([
                            $data['cust_name']   ? "Cust: {$data['cust_name']}" : null,
                            $data['model']       ? "Model: {$data['model']}" : null,
                            $data['kode_berkas'] ? "Part: {$data['kode_berkas']}" : null,
                        ])->filter()->values()->all();
                    }),

                \Filament\Tables\Filters\Filter::make('q')
                    ->label('Cari')
                    ->form([
                        \Filament\Forms\Components\Grid::make()
                            ->columns(12)
                            ->schema([
                                \Filament\Forms\Components\TagsInput::make('terms')
                                    ->label('Kata kunci')
                                    ->placeholder('Ketik lalu Enter untuk menambah')
                                    ->separator(',')
                                    ->reorderable()
                                    ->live(debounce: 300)
                                    ->columnSpan(9),

                                \Filament\Forms\Components\Toggle::make('all')
                                    ->label('All keywords')
                                    ->inline(true)        // toggle + label satu baris, di kanan
                                    ->columnSpan(5),
                            ]),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) use ($tbl): void {
                        $terms = collect($data['terms'] ?? [])
                            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                            ->map(fn ($t) => trim($t))
                            ->unique()
                            ->values()
                            ->all();

                        if (empty($terms)) return;

                        $modeAll = filter_var($data['all'] ?? false, FILTER_VALIDATE_BOOLEAN);

                        $buildOneTermClause = function (\Illuminate\Database\Eloquent\Builder $q2, string $term) use ($tbl): void {
                            $like = '%' . mb_strtolower($term) . '%';

                            $q2->where(function (\Illuminate\Database\Eloquent\Builder $g) use ($like, $tbl) {
                                $g->whereRaw("LOWER({$tbl}.kode_berkas) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.cust_name) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.model) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.nama) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.detail) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.dokumen) LIKE ?", [$like])
                                ->orWhereRaw("LOWER(CAST({$tbl}.keywords AS CHAR)) LIKE ?", [$like]);

                                // relasi lampirans
                                $g->orWhere(function (\Illuminate\Database\Eloquent\Builder $q) use ($like) {
                                    $q->whereHas('lampirans', function (\Illuminate\Database\Eloquent\Builder $l) use ($like) {
                                        $l->where(function (\Illuminate\Database\Eloquent\Builder $lx) use ($like) {
                                            $lx->whereRaw('LOWER(lampirans.nama) LIKE ?', [$like])
                                            ->orWhereRaw('LOWER(lampirans.file) LIKE ?', [$like])
                                            ->orWhereRaw('LOWER(CAST(lampirans.keywords AS CHAR)) LIKE ?', [$like]);
                                        });
                                    });
                                });
                            });
                        };

                        $query->where(function (\Illuminate\Database\Eloquent\Builder $outer) use ($terms, $modeAll, $buildOneTermClause) {
                            if ($modeAll) {
                                foreach ($terms as $term) {
                                    $outer->where(fn ($sub) => $buildOneTermClause($sub, $term));
                                }
                            } else {
                                $outer->where(function ($subOr) use ($terms, $buildOneTermClause) {
                                    foreach ($terms as $term) {
                                        $subOr->orWhere(fn ($sub) => $buildOneTermClause($sub, $term));
                                    }
                                });
                            }
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $tags = collect($data['terms'] ?? [])
                            ->filter(fn ($t) => is_string($t) && trim($t) !== '');
                        return $tags->isNotEmpty() ? 'Cari: '.$tags->implode(', ') : null;
                    }),
            ]) 
            ->actions([
                \Filament\Tables\Actions\Action::make('lampiran')
                    ->label('')
                    ->icon('heroicon-m-paper-clip')
                    ->color('gray')
                    ->size('xs')
                    ->modalHeading('Dokumen Pelengkap')
                    ->modalSubmitAction(false)
                    ->modalWidth('7xl')
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(function (? \Illuminate\Database\Eloquent\Model $record) {
                        if (! $record) return view('tables.rows.lampirans', ['record' => null, 'lampirans' => collect()]);
                        $roots = $record->rootLampirans()->with('childrenRecursive')->orderBy('id')->get();
                        return view('tables.rows.lampirans', ['record' => $record, 'lampirans' => $roots]);
                    })
                    ->tooltip('Dokumen Pelengkap'),

                \Filament\Tables\Actions\ViewAction::make()
                    ->label('')
                    ->icon('heroicon-m-eye')
                    ->modalWidth('7xl')
                    ->tooltip('Lihat'),

                \Filament\Tables\Actions\EditAction::make()
                    ->label('')
                    ->icon('heroicon-m-pencil')
                    ->tooltip('Edit')
                    // ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)
                    ,

                \Filament\Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-m-trash')
                    ->tooltip('Hapus')
                    ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false),

                \Filament\Tables\Actions\Action::make('downloadSource')
                    ->label('')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => route('download.source', [
                        'type' => 'regular',   // ← khusus Regular
                        'id'   => $record->getKey(),
                    ]))
                    ->openUrlInNewTab()
                    ->visible(fn () => \Illuminate\Support\Facades\Gate::allows('download-source'))
                    ->disabled(fn ($record) => blank($record->dokumen_src))
                    ->tooltip(fn ($record) => blank($record->dokumen_src) ? 'File asli belum diunggah' : 'Download file asli')
                    ->extraAttributes(fn ($record) => [
                        'title' => blank($record->dokumen_src) ? 'File asli belum diunggah' : 'Download file asli',
                    ]),
            ])
            ->recordUrl(null)
            ->recordAction('view');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRegular::route('/'),
            'create' => Pages\CreateRegular::route('/create'),
            'edit'   => Pages\EditRegular::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['lampirans']);
        return $query->orderByRaw('LOWER(cust_name) ASC')
                     ->orderByRaw('LOWER(model) ASC');
    }

    public static function canViewAny(): bool
    {
        // samakan izin dengan Berkas/Event
        return \App\Filament\Resources\BerkasResource::canViewAny();
    }
    public static function canCreate(): bool
    {
        return \App\Filament\Resources\BerkasResource::canCreate();
    }
    public static function canDelete($record): bool
    {
        return \App\Filament\Resources\BerkasResource::canDelete($record);
    }
    public static function canDeleteAny(): bool
    {
        return \App\Filament\Resources\BerkasResource::canDeleteAny();
    }
    public static function shouldRegisterNavigation(): bool
    {
        return \App\Filament\Resources\BerkasResource::shouldRegisterNavigation();
    }
}
