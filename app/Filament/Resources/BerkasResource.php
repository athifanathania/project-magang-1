<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BerkasResource\Pages;
use App\Filament\Resources\BerkasResource\RelationManagers;
use App\Models\Berkas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\View as ViewField;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\TextInput as FTTextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Arr;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Panel;
// use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Str;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use App\Filament\Support\RowClickViewForNonEditors;
use App\Filament\Support\FileCell;
use Illuminate\Validation\Rule;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;

class BerkasResource extends Resource
{
    protected static ?string $model = Berkas::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Event';
    protected static ?string $navigationGroup = 'Dokumen Eksternal';

    protected static ?string $modelLabel = 'dokumen';   
    protected static ?int    $navigationSort  = 1;
    protected static ?string $pluralModelLabel = 'Event'; 

    use RowClickViewForNonEditors, FileCell;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('thumbnail')
                    ->label('Gambar')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null  => 'Bebas',
                        '1:1' => 'Persegi',
                        '4:3' => '4:3',
                        '16:9'=> '16:9',
                    ])
                    ->imageResizeMode('cover')
                    ->imageResizeUpscale(false)
                    ->disk('public')
                    ->directory('berkas/thumbnails')
                    ->imagePreviewHeight('150')
                    ->openable()          // thumbnail tetap public
                    ->downloadable()
                    ->visibility('public')
                    ->preserveFilenames()
                    ->maxSize(10240)
                    ->nullable(),
                    
                TextInput::make('cust_name')
                    ->label('Customer Name')
                    ->placeholder('mis. Suzuki')
                    ->datalist(['Suzuki','Yamaha','FCC Indonesia','Astemo','IMC Tekno Id'])
                    ->maxLength(100)
                    ->required(fn (string $context) => $context === 'create'),

                TextInput::make('model')
                    ->label('Model')
                    ->placeholder('mis. YTB')
                    ->datalist(['YTB'])
                    ->maxLength(100),

                TextInput::make('kode_berkas')
                    ->label('Part No')
                    ->required()
                    ->rules(function (Get $get, ?Model $record) {
                        $detail = mb_strtolower(trim((string) $get('detail')));
                        return Rule::unique('berkas', 'kode_berkas')
                            ->where(fn ($q) => $q->whereRaw('LOWER(TRIM(`detail`)) = ?', [$detail]))
                            ->ignore($record?->getKey());
                    })
                    ->validationMessages([
                        'unique' => 'Dokumen yang ditambahkan sudah tersedia di tabel Event.',
                    ]),


                TextInput::make('nama')
                    ->label('Part Name')
                    ->required(),
                
                TextInput::make('detail')
                    ->label('Detail Event')
                    ->placeholder('mis. Document')
                    ->required()
                    ->datalist(['Document','Part'])
                    ->rules(function (Get $get, ?\Illuminate\Database\Eloquent\Model $record) {
                        $kode = mb_strtolower(trim((string) $get('kode_berkas')));
                        return Rule::unique('berkas', 'detail')
                            ->where(fn ($q) => $q->whereRaw('LOWER(TRIM(`kode_berkas`)) = ?', [$kode]))
                            ->ignore($record?->getKey());
                    })
                    ->validationMessages([
                        'unique' => 'Dokumen yang ditambahkan sudah tersedia di tabel Event.',
                    ]),

                TagsInput::make('keywords')
                    ->label('Kata Kunci Part')
                    ->placeholder('Ketik lalu Enter')
                    ->separator(',')
                    ->reorderable()
                    ->columnSpanFull(),

                // ===== Dokumen (PRIVATE) =====
                FileUpload::make('dokumen')
                    ->label('Dokumen')
                    ->disk('private')
                    ->directory('berkas')
                    ->preserveFilenames()
                    ->rules(['file'])
                    ->previewable(true)
                    ->downloadable(false)
                    ->openable(false)
                    ->required(fn (string $context) => $context === 'create')
                    ->saveUploadedFileUsing(function ($file, $record) {
                        if ($record) {
                            $ver = $record->addVersionFromUpload($file);
                            return $ver['file_path'] ?? $record->dokumen;
                        }
                        return $file->store('berkas/tmp', 'private');
                    })
                    ->deleteUploadedFileUsing(fn () => null)
                    ->hintAction(
                        \Filament\Forms\Components\Actions\Action::make('openFile')
                            ->label('Buka file')
                            ->url(
                                fn ($record) => ($record && $record->dokumen)
                                    ? route('media.berkas', $record)
                                    : null,
                                shouldOpenInNewTab: true
                            )
                            ->visible(fn ($record) =>
                                filled($record?->dokumen)
                                && (auth()->user()?->hasAnyRole(['Admin','Editor','Staff']) ?? false)
                            )
                        ),

                FileUpload::make('dokumen_src')
                    ->label('File Asli (Admin saja)')
                    ->disk('private')
                    ->directory('berkas/_source')     // pisahkan folder sumber
                    ->preserveFilenames()
                    ->rules(['nullable','file'])
                    ->previewable(true)
                    ->downloadable(false)             // jangan expose URL publik
                    ->openable(false)
                    ->preserveFilenames()
                    ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false),


                // =========================
                // Lampiran (disarankan kelola via tabel/aksi)
                // =========================
                \Filament\Forms\Components\Section::make('Dokumen Pelengkap')
                    ->description('Kelola dokumen pelengkap melalui tombol "Tambah Lampiran" di panel tabel.'),
                Section::make('Riwayat dokumen')
                    ->visible(fn (string $context) => $context === 'view') // hanya muncul di modal View
                    ->schema([
                        ViewField::make('dokumen_history')
                            ->view('tables.rows.berkas-history') // blade di langkah 3
                            ->columnSpanFull(),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyRowClickPolicy($table)
            // >>> default order: cust_name, lalu model (hanya kalau user belum set sort)
            ->modifyQueryUsing(function (Builder $q) {
                $hasSort = filled(request()->input('tableSortColumn'));
                if (! $hasSort) {
                    $q->orderBy('cust_name')->orderBy('model');
                }
            })   
            ->columns([
                TextColumn::make('cust_name')
                    ->label('Cust Name')
                    ->sortable()
                    ->limit(16)
                    ->tooltip(fn (?string $state) => $state)
                    ->grow(false)                                   // << kunci agar tidak melebar
                    ->width('8rem')                                 // opsional, bisa 7–10rem
                    ->extraHeaderAttributes(['class' => 'w-[8rem]'])
                    ->extraCellAttributes([
                        'class' => 'w-[8rem] max-w-[8rem] whitespace-nowrap truncate',
                    ]),

                TextColumn::make('model')
                    ->label('Model')
                    ->sortable()
                    ->extraCellAttributes(['class' => 'max-w-[7rem] whitespace-normal break-words']),

                TextColumn::make('kode_berkas')
                    ->label('Part No')
                    ->extraCellAttributes(['class' => 'max-w-[10rem] whitespace-normal break-words'])
                    ->sortable(),
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->disk('public')
                    // biar ikut rasio asli, hanya dibatasi ukuran maks
                    ->extraImgAttributes([
                        'class' => 'h-auto w-auto max-h-24 max-w-32 object-contain rounded-none',
                    ])
                    // beri lebar sel supaya gambar punya ruang
                    ->extraCellAttributes([
                        'class' => 'w-[140px]',
                    ])
                    ->defaultImageUrl(asset('images/placeholder.png'))
                    ->sortable(false)
                    ->searchable(false),
                TextColumn::make('nama')
                        ->label('Part Name')
                        ->sortable()
                        ->wrap()
                        ->extraCellAttributes([
                            'class' => 'max-w-[18rem] whitespace-normal break-words',
                        ]),
                TextColumn::make('detail')
                    ->label('Detail Event')
                    ->wrap()
                    ->extraCellAttributes([
                        'class' => 'max-w-[14rem] whitespace-normal break-words',
                    ]),
                ViewColumn::make('keywords_display')
                    ->label('Kata Kunci')
                    ->state(fn ($record) => $record->keywords)
                    ->view('tables.columns.keywords-grid')
                    ->extraCellAttributes([
                        'class' => 'max-w-[22rem] whitespace-normal break-words',
                    ]),
                static::fileTextColumn('dokumen', function ($record) {
                    return $record instanceof \App\Models\Regular
                        ? route('media.regular', $record)
                        : route('media.berkas', $record);
                })
                    ->label('File')
                    ->extraCellAttributes(['class' => 'text-xs']),
            ]) 
            ->filters([
                // === Filter select: Cust Name ===
                Tables\Filters\SelectFilter::make('cust_name')
                    ->label('Cust Name')
                    ->options(fn () =>
                        \App\Models\Berkas::query()
                            ->whereNotNull('cust_name')
                            ->distinct()
                            ->orderBy('cust_name')
                            ->pluck('cust_name', 'cust_name')
                            ->all()
                    )
                    ->preload()
                    ->searchable(), // bisa ketik untuk cari option

                // === Filter select: Model ===
                Tables\Filters\SelectFilter::make('model')
                    ->label('Model')
                    ->options(fn () =>
                        \App\Models\Berkas::query()
                            ->whereNotNull('model')
                            ->distinct()
                            ->orderBy('model')
                            ->pluck('model', 'model')
                            ->all()
                    )
                    ->preload()
                    ->searchable(),

                // === Filter select: Part No (kode_berkas) ===
                Tables\Filters\SelectFilter::make('kode_berkas')
                    ->label('Part No')
                    ->options(fn () =>
                        \App\Models\Berkas::query()
                            ->whereNotNull('kode_berkas')
                            ->distinct()
                            ->orderBy('kode_berkas')
                            ->pluck('kode_berkas', 'kode_berkas')
                            ->all()
                    )
                    ->preload()
                    ->searchable(),

                    Filter::make('q')
                        ->label('Cari')
                        ->form([
                            TagsInput::make('terms')
                                ->label('Kata kunci')
                                ->placeholder('Ketik lalu Enter untuk menambah')
                                ->reorderable()
                                ->separator(',')
                                ->live(debounce: 500)
                                // >>> auto-apply tanpa klik Apply
                                ->afterStateUpdated(function ($state, $set = null, $get = null, $refresh = null) {
                                    // $refresh tersedia di Filament v3 → jalankan bila ada
                                    if (is_callable($refresh)) $refresh();
                                }),

                            Toggle::make('all')
                                ->label('Cocokkan semua keyword (mode ALL)')
                                ->inline(false)
                                ->live()
                                // >>> auto-apply saat toggle berubah
                                ->afterStateUpdated(function ($state, $set = null, $get = null, $refresh = null) {
                                    if (is_callable($refresh)) $refresh();
                                }),
                        ])
                        ->query(function (Builder $query, array $data): void {
                            $terms = collect($data['terms'] ?? [])
                                ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                                ->map(fn ($t) => trim($t))
                                ->unique()
                                ->values()
                                ->all();

                            if (empty($terms)) return;

                            $modeAll = filter_var($data['all'] ?? false, FILTER_VALIDATE_BOOLEAN);

                            $buildOneTermClause = function (Builder $q2, string $term): void {
                                $like = '%' . mb_strtolower($term) . '%';

                                // Grupkan semua OR dalam satu kurung
                                $q2->where(function (Builder $g) use ($like) {
                                    $g->whereRaw('LOWER(berkas.kode_berkas) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(berkas.cust_name) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(berkas.model) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(berkas.nama) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(berkas.detail) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(berkas.dokumen) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(CAST(berkas.keywords AS CHAR)) LIKE ?', [$like]);

                                    // Relasi dibungkus agar tetap di dalam kurung
                                    $g->orWhere(function (Builder $q) use ($like) {
                                        $q->whereHas('lampirans', function (Builder $l) use ($like) {
                                            $l->where(function (Builder $lx) use ($like) {
                                                $lx->whereRaw('LOWER(lampirans.nama) LIKE ?', [$like])
                                                ->orWhereRaw('LOWER(lampirans.file) LIKE ?', [$like])
                                                ->orWhereRaw('LOWER(CAST(lampirans.keywords AS CHAR)) LIKE ?', [$like]);
                                            });
                                        });
                                    });
                                });
                            };

                            $query->where(function (Builder $outer) use ($terms, $modeAll, $buildOneTermClause) {
                                if ($modeAll) {
                                    foreach ($terms as $term) {
                                        $outer->where(fn (Builder $sub) => $buildOneTermClause($sub, $term));
                                    }
                                } else {
                                    $outer->where(function (Builder $subOr) use ($terms, $buildOneTermClause) {
                                        foreach ($terms as $term) {
                                            $subOr->orWhere(fn (Builder $sub) => $buildOneTermClause($sub, $term));
                                        }
                                    });
                                }
                            });
                        })
                        ->indicateUsing(function (array $data): ?string {
                            $terms = collect($data['terms'] ?? [])
                                ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                                ->map(fn ($t) => trim($t));

                            return $terms->isNotEmpty()
                                ? 'Cari: ' . $terms->implode(', ')
                                : null;
                        }),
                ])
                ->actions([
                    Action::make('lampiran')
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
           
                    ViewAction::make()
                        ->label('')
                        ->icon('heroicon-m-eye')
                        ->modalWidth('7xl')
                        ->tooltip('Lihat'),

                    // Edit/Delete: hanya Admin/Editor – gunakan nullsafe (?? false)
                    EditAction::make()
                        ->label('')
                        ->icon('heroicon-m-pencil')
                        ->tooltip('Edit')
                        ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Editor']) ?? false),

                    DeleteAction::make()
                        ->label('')
                        ->icon('heroicon-m-trash')
                        ->tooltip('Hapus')
                        ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false),

                    Action::make('downloadSource')
                        ->label('')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn ($record) => route('download.source', ['type' => 'berkas','id'=>$record->getKey()]))
                        ->openUrlInNewTab()
                        ->visible(fn () => \Illuminate\Support\Facades\Gate::allows('download-source'))
                        ->disabled(fn ($record) => blank($record->dokumen_src))
                        ->tooltip(fn ($record) => blank($record->dokumen_src)
                            ? 'File asli belum diunggah'
                            : 'Download file asli'
                        )
                        ->extraAttributes(fn ($record) => [
                            'title' => blank($record->dokumen_src)
                                ? 'File asli belum diunggah'
                                : 'Download file asli',
                        ]),
                ])
            
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        //
        return[];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBerkas::route('/'),
            'create' => Pages\CreateBerkas::route('/create'),
            'edit' => Pages\EditBerkas::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['lampirans']);

        if (optional(\Filament\Facades\Filament::getCurrentPanel())->getId() === 'public') {
            $query->where('is_public', true);
        }

        // Default sort: 1) cust_name ASC, 2) model ASC
        return $query
            ->orderByRaw('LOWER(cust_name) ASC')
            ->orderByRaw('LOWER(model) ASC');
    }

    public static function canViewAny(): bool
    {
        return optional(\Filament\Facades\Filament::getCurrentPanel())->getId() === 'public'
            ? true
            : (auth()->user()?->can('berkas.view') ?? false);
    }


    public static function canCreate(): bool
    {
        return auth()->user()?->can('berkas.create') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return optional(\Filament\Facades\Filament::getCurrentPanel())->getId() === 'public'
            ? true
            : (auth()->user()?->can('berkas.view') ?? false);
    }
}
