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

class BerkasResource extends Resource
{
    protected static ?string $model = Berkas::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Regular';

    protected static ?string $modelLabel = 'dokumen';   // singular
    protected static ?string $pluralModelLabel = 'Regular'; // plural

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('customer_name')
                    ->label('Customer Name')
                    ->placeholder('mis. Suzuki')
                    ->datalist(['Suzuki', 'Yamaha', 'FCC Indonesia', 'Astemo', 'IMC']) 
                    ->maxLength(100)
                    ->required(),

                TextInput::make('model')
                    ->label('Model')
                    ->placeholder('mis. YTB')
                    ->datalist(['YTB'])
                    ->maxLength(100),

                TextInput::make('kode_berkas')
                    ->label('Part No')
                    ->required(),

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

                TextInput::make('nama')
                    ->label('Part Name')
                    ->required(),
                TextInput::make('detail')
                    ->placeholder('mis. Document')
                    ->datalist(['Document', 'Part']),

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
                    ->downloadable(false) // JANGAN generate URL publik
                    ->openable(false)     // JANGAN open via Storage::url
                    ->getUploadedFileNameForStorageUsing(fn ($file) =>
                        now()->format('Ymd_His') . '-' . Str::random(6) . '-' . $file->getClientOriginalName()
                    )
                    ->required(fn (string $context) => $context === 'create')
                    ->hintAction(
                        FormAction::make('openFile')
                            ->label('Buka file')
                            ->url(
                                fn ($record) => ($record && $record->dokumen)
                                    ? route('media.berkas', $record) // /media/berkas/{berkas}
                                    : null,
                                shouldOpenInNewTab: true
                            )
                            ->visible(fn ($record) => filled($record?->dokumen))
                    ),

                // =========================
                // Lampiran (disarankan kelola via tabel/aksi)
                // =========================
                \Filament\Forms\Components\Section::make('Lampiran')
                    ->description('Kelola lampiran melalui tombol "Lampiran" / "Kelola Lampiran" di tabel.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Cust Name')
                    ->sortable()
                    ->searchable()
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
                    ->extraCellAttributes(['class' => 'max-w-[7rem] whitespace-normal break-words'])
                    ->searchable(),

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
                    ->label('Detail')
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
                TextColumn::make('dokumen_link')
                    ->label('File')
                    ->state(fn ($record) => $record->dokumen ? '📂' : '-')
                    ->url(fn ($record) => $record->dokumen ? route('media.berkas', $record) : null, shouldOpenInNewTab: true)
                    ->color(fn ($record) => $record->dokumen ? 'primary' : null)
                    ->extraAttributes(['class' => 'text-blue-600 hover:underline'])
                    ->extraCellAttributes(['class' => 'text-xs'])   // <<< kecilkan font
                    ->sortable(false)
                    ->searchable(false),
                ])
                ->filters([
                    Filter::make('q')
                        ->label('Cari')
                        ->form([
                            // === TAGS INPUT: tambah keyword satu per satu jadi chip ===
                            TagsInput::make('terms')
                                ->label('Kata kunci')
                                ->placeholder('Ketik lalu Enter untuk menambah')
                                ->reorderable()
                                ->separator(',') // opsional: bisa paste "a, b, c"
                                ->suggestions([]), // tidak ada default suggestion
                            Toggle::make('all')
                                ->label('Cocokkan semua keyword (mode ALL)')
                                ->inline(false),
                        ])
                        ->query(function (Builder $query, array $data): void {
                            $terms = collect($data['terms'] ?? [])
                                ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                                ->map(fn ($t) => trim($t))
                                ->unique()
                                ->values()
                                ->all();

                            if (empty($terms)) return;

                            $modeAll = (bool) ($data['all'] ?? false);

                            $buildOneTermClause = function (Builder $q2, string $term): void {
                                $termLower = mb_strtolower($term);
                                $likeLower = "%{$termLower}%";

                                // ====== BERKAS (kolom string biasa) ======
                                $q2->whereRaw('LOWER(berkas.kode_berkas) LIKE ?', [$likeLower])
                                ->orWhereRaw('LOWER(berkas.customer_name) LIKE ?', [$likeLower])
                                ->orWhereRaw('LOWER(berkas.model)         LIKE ?', [$likeLower])
                                ->orWhereRaw('LOWER(berkas.nama)        LIKE ?', [$likeLower])
                                ->orWhereRaw('LOWER(berkas.detail)      LIKE ?', [$likeLower])
                                ->orWhereRaw('LOWER(berkas.dokumen)     LIKE ?', [$likeLower])

                                // ====== BERKAS (JSON keywords → CAST ke CHAR) ======
                                ->orWhereRaw('LOWER(CAST(berkas.keywords AS CHAR)) LIKE ?', [$likeLower])

                                // ====== LAMPIRANS (relasi) ======
                                ->orWhereHas('lampirans', function (Builder $l) use ($likeLower) {
                                    $l->whereRaw('LOWER(lampirans.nama) LIKE ?', [$likeLower])
                                        ->orWhereRaw('LOWER(lampirans.file) LIKE ?', [$likeLower])

                                        // LAMPIRANS (JSON keywords → CAST ke CHAR)
                                        ->orWhereRaw('LOWER(CAST(lampirans.keywords AS CHAR)) LIKE ?', [$likeLower]);
                                });
                            };

                            $query->where(function (Builder $outer) use ($terms, $modeAll, $buildOneTermClause): void {
                                if ($modeAll) {
                                    foreach ($terms as $term) {
                                        $outer->where(fn (Builder $sub) => $buildOneTermClause($sub, $term));
                                    }
                                } else {
                                    $outer->where(function (Builder $subOr) use ($terms, $buildOneTermClause): void {
                                        foreach ($terms as $term) {
                                            $subOr->orWhere(fn (Builder $sub) => $buildOneTermClause($sub, $term));
                                        }
                                    });
                                }
                            });
                        })
                        // tampilkan chips yang aktif di pill indikator filter
                        ->indicateUsing(function (array $data): ?string {
                            $terms = collect($data['terms'] ?? [])
                                ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                                ->map(fn ($t) => trim($t))
                                ->values();

                            return $terms->isNotEmpty()
                                ? 'Cari: ' . $terms->implode(', ')
                                : null;
                        }),
                ])
                ->actions([
                    Action::make('lampiran')
                        ->label('Lampiran')
                        ->icon('heroicon-m-paper-clip')
                        ->color('gray')
                        ->size('xs')                   // << kecilkan font
                        ->modalHeading('Lampiran')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalContent(function (\App\Models\Berkas $record) {
                            $roots = $record->rootLampirans()->with('childrenRecursive')->orderBy('id')->get();
                            return view('tables.rows.lampirans', ['record' => $record, 'lampirans' => $roots]);
                        }),
           
                    ViewAction::make()
                        ->label('')
                        ->icon('heroicon-m-eye')
                        ->tooltip('Lihat')
                        ->modalContent(function (\App\Models\Berkas $record) {
                            return view('tables.rows.berkas-view', ['record' => $record]);
                        // berkas-view.blade.php berisi form readonly + include('tables.rows.berkas-history')
                        }),

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
                        ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Editor']) ?? false),
                ])
            
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Editor']) ?? false),
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

        return $query;
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

    public static function shouldRegisterNavigation(): bool
    {
        return optional(\Filament\Facades\Filament::getCurrentPanel())->getId() === 'public'
            ? true
            : (auth()->user()?->can('berkas.view') ?? false);
    }
}
