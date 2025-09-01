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

class BerkasResource extends Resource
{
    protected static ?string $model = Berkas::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Dokumen';

    protected static ?string $modelLabel = 'dokumen';   // singular
    protected static ?string $pluralModelLabel = 'Dokumen'; // plural

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('kode_berkas')
                    ->required(),

                FileUpload::make('thumbnail')
                    ->label('Gambar')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null => 'Bebas',
                        '1:1' => 'Persegi',
                        '4:3' => '4:3',
                        '16:9' => '16:9',
                    ])
                    ->imageResizeMode('cover')
                    ->imageResizeUpscale(false)
                    ->disk('public')
                    ->directory('berkas/thumbnails')
                    ->imagePreviewHeight('150')
                    ->openable()
                    ->downloadable()
                    ->visibility('public')
                    ->preserveFilenames()
                    ->maxSize(10240)
                    ->nullable(),

                TextInput::make('nama')
                    ->required(),
                TextInput::make('detail'),

                TagsInput::make('keywords')
                    ->label('Kata Kunci (Berkas)')
                    ->placeholder('Ketik lalu Enter')
                    ->separator(',')
                    ->reorderable()
                    ->columnSpanFull(),

                FileUpload::make('dokumen')
                    ->disk('public')
                    ->directory('berkas')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->previewable(true)
                    ->downloadable()
                    ->rules(['nullable', 'file'])
                    ->required(),

                    // =========================
                    // Lampiran (induk + anak)
                    // =========================
                    Section::make('Lampiran')
                        ->description('Kelola lampiran melalui tombol "Lampiran" / "Kelola Lampiran" di tabel.')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_berkas')->label('Kode Berkas')->sortable(),
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
                TextColumn::make('nama')->label('Nama')->sortable(),
                TextColumn::make('detail')->label('Detail')->limit(80),
                ViewColumn::make('keywords_display')
                    ->label('Kata Kunci')
                    ->state(fn ($record) => $record->keywords)
                    ->view('tables.columns.keywords-grid'),
                TextColumn::make('dokumen_link')          // <- kolom virtual, bukan field DB
                    ->label('File')
                    ->state(fn ($record) => $record->dokumen ? '📂 Lihat File' : '-') 
                    ->url(
                        fn ($record) => $record->dokumen
                            ? asset('storage/'.$record->dokumen)
                            : null,
                        shouldOpenInNewTab: true
                    )
                ->color(fn ($record) => $record->dokumen ? 'primary' : null)
                ->extraAttributes(['class' => 'text-blue-600 hover:underline'])
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
                        ->modalHeading('Lampiran')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalContent(function (\App\Models\Berkas $record) {
                            $roots = $record->rootLampirans()->with('childrenRecursive')->orderBy('id')->get();
                            return view('tables.rows.lampirans', ['record' => $record, 'lampirans' => $roots]);
                        }),
           
                    ViewAction::make()
                        ->label('')
                        ->icon('heroicon-m-eye') // bold eye
                        ->tooltip('Lihat'), // tooltip saat hover

                    EditAction::make()
                        ->label('')
                        ->icon('heroicon-m-pencil') // bold pencil
                        ->tooltip('Edit'),

                    DeleteAction::make()
                        ->label('')
                        ->icon('heroicon-m-trash') // bold trash
                        ->tooltip('Hapus'),
                ])
            ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
        return parent::getEloquentQuery()->with(['lampirans']); 
    }

}
