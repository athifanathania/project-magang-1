<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventCustomerResource\Pages;
use App\Models\EventCustomer; // Pastikan Model ini sudah dibuat
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\View as ViewField;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Forms\Components\TagsInput;
use App\Filament\Support\RowClickViewForNonEditors;
use App\Filament\Support\FileCell;
use Illuminate\Validation\Rule;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Select;

class EventCustomerResource extends Resource
{
    // Arahkan ke model baru agar data terpisah dari Berkas (Event Document)
    protected static ?string $model = EventCustomer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group'; // Icon sedikit beda biar mudah dibedakan visualnya (opsional)

    protected static ?string $navigationLabel = 'Event Customer';
    protected static ?string $navigationGroup = 'Dokumen Eksternal';

    protected static ?string $modelLabel = 'Event Customer';   
    protected static ?int    $navigationSort  = 4;  // Urutan setelah Event Document
    protected static ?string $pluralModelLabel = 'Event Customer'; 

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
                    // Simpan di folder terpisah agar rapi
                    ->directory('event_customers/thumbnails')
                    ->imagePreviewHeight('150')
                    ->openable()          
                    ->downloadable()
                    ->visibility('public')
                    ->preserveFilenames()
                    ->maxSize(10240)
                    ->nullable(),
                    
                Select::make('cust_name')
                    ->label('Customer Name')
                    ->placeholder('Pilih Customer')
                    ->options(\App\Models\Customer::query()->pluck('name', 'name'))
                    ->searchable() 
                    ->preload()    
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
                        // Ubah 'berkas' ke nama tabel model EventCustomer, misal 'event_customers'
                        return Rule::unique('event_customers', 'kode_berkas')
                            ->where(fn ($q) => $q->whereRaw('LOWER(TRIM(`detail`)) = ?', [$detail]))
                            ->ignore($record?->getKey());
                    })
                    ->validationMessages([
                        'unique' => 'Data yang ditambahkan sudah tersedia di tabel Event Customer.',
                    ]),

                TextInput::make('nama')
                    ->label('Part Name')
                    ->required(),
                
                // === PERUBAHAN LABEL DI SINI ===
                TextInput::make('detail')
                    ->label('Poin Audit')   // Diganti dari Detail Event
                    ->placeholder('mis. Document')
                    ->required()
                    ->datalist(['Document','Part'])
                    ->rules(function (Get $get, ?\Illuminate\Database\Eloquent\Model $record) {
                        $kode = mb_strtolower(trim((string) $get('kode_berkas')));
                        // Ubah 'berkas' ke nama tabel model EventCustomer
                        return Rule::unique('event_customers', 'detail')
                            ->where(fn ($q) => $q->whereRaw('LOWER(TRIM(`kode_berkas`)) = ?', [$kode]))
                            ->ignore($record?->getKey());
                    })
                    ->validationMessages([
                        'unique' => 'Data yang ditambahkan sudah tersedia di tabel Event Customer.',
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
                    // Folder penyimpanan dipisah
                    ->directory('event_customers')
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

                        $filename = time() . '_' . $file->getClientOriginalName(); 
                        return $file->storeAs('event_customers/tmp', $filename, 'private');
                    })
                    ->deleteUploadedFileUsing(fn () => null)
                    ->hintAction(
                        \Filament\Forms\Components\Actions\Action::make('openFile')
                            ->label('Buka file')
                            ->url(
                                fn ($record) => ($record && $record->dokumen)
                                    // Pastikan route ini ada atau sesuaikan
                                    ? route('media.event_customer', $record)
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
                    ->directory('event_customers/_source')     
                    ->preserveFilenames()
                    ->rules(['nullable','file'])
                    ->previewable(true)
                    ->downloadable(false)            
                    ->openable(false)
                    ->preserveFilenames()
                    ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false),


                // =========================
                // Lampiran 
                // =========================
                \Filament\Forms\Components\Section::make('Dokumen Pelengkap')
                    ->description('Kelola dokumen pelengkap melalui tombol "Tambah Lampiran" di panel tabel.'),
                Section::make('Riwayat dokumen')
                    ->visible(fn (string $context) => $context === 'view')
                    ->schema([
                        ViewField::make('dokumen_history')
                            ->view('tables.rows.berkas-history') // Bisa re-use view yang sama jika struktur datanya sama
                            ->columnSpanFull(),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyRowClickPolicy($table)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->striped()
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Dropdown)
            ->columns([
                TextColumn::make('cust_name')
                    ->label('Cust Name')
                    ->sortable()
                    ->limit(16)
                    ->tooltip(fn (?string $state) => $state)
                    ->width('8rem') 
                    ->grow(false)   
                    ->extraCellAttributes([
                        'class' => 'whitespace-nowrap truncate', 
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
                    ->extraImgAttributes([
                        'class' => 'h-auto w-auto max-h-24 max-w-32 object-contain rounded-none',
                    ])
                    ->extraCellAttributes([
                        'class' => 'w-[140px]', 
                    ])
                    ->extraHeaderAttributes([
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
                    ->label('Poin Audit') 
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
                    return route('media.event_customer', $record);
                })
                    ->label('File')
                    ->extraCellAttributes(['class' => 'text-xs']),
            ]) 
            ->filters([
                // =================================================================
                // 1. FILTER HIERARKI (3 TINGKAT: Cust -> Model -> PartNo-Name)
                // =================================================================
                \Filament\Tables\Filters\Filter::make('hierarchy')
                    ->label('Filter Spesifik')
                    ->form([
                        // LEVEL 1: CUST NAME
                        \Filament\Forms\Components\Select::make('cust_name')
                            ->label('Cust Name')
                            ->options(fn () => \App\Models\EventCustomer::query()
                                ->whereNotNull('cust_name')->distinct()->orderBy('cust_name')
                                ->pluck('cust_name', 'cust_name'))
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => [
                                $set('model', null), 
                                $set('part_info', null),
                            ]),

                        // LEVEL 2: MODEL
                        \Filament\Forms\Components\Select::make('model')
                            ->label('Model')
                            ->options(fn (\Filament\Forms\Get $get) => \App\Models\EventCustomer::query()
                                ->when($get('cust_name'), fn ($q) => $q->where('cust_name', $get('cust_name')))
                                ->whereNotNull('model')->distinct()->orderBy('model')
                                ->pluck('model', 'model'))
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => [
                                $set('part_info', null),
                            ]),

                        // LEVEL 3: PART NO - PART NAME (GABUNGAN)
                        \Filament\Forms\Components\Select::make('part_info')
                            ->label('Part No - Part Name')
                            ->options(fn (\Filament\Forms\Get $get) => \App\Models\EventCustomer::query()
                                ->when($get('cust_name'), fn ($q) => $q->where('cust_name', $get('cust_name')))
                                ->when($get('model'), fn ($q) => $q->where('model', $get('model')))
                                ->whereNotNull('kode_berkas')
                                ->select('kode_berkas', 'nama')
                                ->distinct()
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    return [$item->kode_berkas => "{$item->kode_berkas} - {$item->nama}"];
                                }))
                            ->searchable()
                            ->preload()
                            ->reactive(),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        return $query
                            ->when($data['cust_name'] ?? null, fn ($q, $v) => $q->where('cust_name', $v))
                            ->when($data['model'] ?? null, fn ($q, $v) => $q->where('model', $v))
                            ->when($data['part_info'] ?? null, fn ($q, $v) => $q->where('kode_berkas', $v));
                    })
                    ->indicateUsing(function (array $data): array {
                        return collect([
                            ($data['cust_name'] ?? null) ? "Cust: {$data['cust_name']}" : null,
                            ($data['model'] ?? null)     ? "Model: {$data['model']}" : null,
                            ($data['part_info'] ?? null) ? "Part: {$data['part_info']}" : null,
                        ])->filter()->values()->all();
                    }),

                // =================================================================
                // 2. FILTER PENCARIAN CUSTOM (Include Lampiran)
                // =================================================================
                \Filament\Tables\Filters\Filter::make('q')
                    ->label('Cari')
                    ->form([
                        \Filament\Forms\Components\Grid::make()
                            ->columns(1)
                            ->schema([
                                \Filament\Forms\Components\TagsInput::make('terms')
                                    ->label('Kata kunci')
                                    ->placeholder('Ketik & Enter')
                                    ->separator(',')
                                    ->reorderable()
                                    ->live(debounce: 300),

                                \Filament\Forms\Components\Toggle::make('all')
                                    ->label('Match All Keywords') 
                                    ->inline(true)
                                    ->helperText('Aktifkan untuk mencari semua kata (AND)'),
                            ]),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): void {
                        $terms = collect($data['terms'] ?? [])->filter(fn ($t) => filled($t))->unique()->values();
                        if ($terms->isEmpty()) return;

                        $tbl = $query->getModel()->getTable();
                        $modeAll = filter_var($data['all'] ?? false, FILTER_VALIDATE_BOOLEAN);

                        $buildOneTermClause = function ($q2, $term) use ($tbl) {
                            $like = '%' . mb_strtolower(trim($term)) . '%';

                            $q2->where(function ($g) use ($like, $tbl) {
                                // A. Cari di tabel Induk
                                $g->whereRaw("LOWER({$tbl}.kode_berkas) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.cust_name) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.model) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.nama) LIKE ?", [$like])
                                ->orWhereRaw("LOWER({$tbl}.detail) LIKE ?", [$like])
                                ->orWhereRaw("LOWER(CAST({$tbl}.keywords AS CHAR)) LIKE ?", [$like]);

                                // B. Cari di Relasi Lampiran
                                $g->orWhereHas('lampirans', function ($l) use ($like) {
                                    $l->where(function ($lx) use ($like) {
                                        $lx->whereRaw('LOWER(nama) LIKE ?', [$like])
                                        ->orWhereRaw('LOWER(file) LIKE ?', [$like])
                                        ->orWhereRaw('LOWER(CAST(keywords AS CHAR)) LIKE ?', [$like]);
                                    });
                                });
                            });
                        };

                        $query->where(function ($outer) use ($terms, $modeAll, $buildOneTermClause) {
                            if ($modeAll) {
                                foreach ($terms as $term) $outer->where(fn ($sub) => $buildOneTermClause($sub, $term));
                            } else {
                                $outer->where(function ($subOr) use ($terms, $buildOneTermClause) {
                                    foreach ($terms as $term) $subOr->orWhere(fn ($sub) => $buildOneTermClause($sub, $term));
                                });
                            }
                        });
                    })
                    ->indicateUsing(fn (array $data) => count($data['terms'] ?? []) ? 'Cari: ' . implode(', ', $data['terms']) : null),
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
                    ->modalContent(function (?Model $record) {
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
                    ->url(fn ($record) => route('download.source', ['type' => 'event_customer','id'=>$record->getKey()]))
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
            ])
            ->recordUrl(null)
            ->recordAction('view');
    }
    
    public static function getRelations(): array
    {
        return[];
    }

    public static function getPages(): array
    {
        // Pastikan Anda membuat file Pages ini di folder EventCustomerResource/Pages
        return [
            'index' => Pages\ListEventCustomers::route('/'),
            'create' => Pages\CreateEventCustomer::route('/create'),
            'edit' => Pages\EditEventCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['lampirans']);

        if (optional(\Filament\Facades\Filament::getCurrentPanel())->getId() === 'public') {
            $query->where('is_public', true);
        }

        return $query
            ->orderByRaw('LOWER(cust_name) ASC')
            ->orderByRaw('LOWER(model) ASC');
    }

    // public static function canViewAny(): bool
    // {
    //     return optional(\Filament\Facades\Filament::getCurrentPanel())->getId() === 'public'
    //         ? true
    //         // Sesuaikan permission policy Anda, misal 'event_customer.view'
    //         : (auth()->user()?->can('event_customer.view') ?? false);
    // }


    // public static function canCreate(): bool
    // {
    //     return auth()->user()?->can('event_customer.create') ?? false;
    // }

    // public static function canDelete($record): bool
    // {
    //     return auth()->user()?->hasRole('Admin') ?? false;
    // }

    // public static function canDeleteAny(): bool
    // {
    //     return auth()->user()?->hasRole('Admin') ?? false;
    // }

    // public static function shouldRegisterNavigation(): bool
    // {
    //     return optional(\Filament\Facades\Filament::getCurrentPanel())->getId() === 'public'
    //         ? true
    //         : (auth()->user()?->can('event_customer.view') ?? false);
    // }
}