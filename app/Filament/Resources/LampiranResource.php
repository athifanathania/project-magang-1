<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LampiranResource\Pages;
use App\Filament\Resources\LampiranResource\RelationManagers;
use App\Models\Lampiran;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;
use Filament\Tables\Columns\TagsColumn;
use Illuminate\Database\Eloquent\Casts\Attribute;

class LampiranResource extends Resource
{
    // protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Lampiran::class;

    protected static ?string $navigationIcon = 'heroicon-m-paper-clip';

    protected static ?string $navigationLabel = 'Lampiran';

    protected static ?string $modelLabel = 'lampiran';
    protected static ?string $pluralModelLabel = 'Lampiran';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('berkas_id')
                ->label('Dokumen')
                ->relationship('berkas', 'nama')
                ->searchable()
                ->preload()
                ->required()
                ->default(request('berkas_id')), // prefill dari URL

            Forms\Components\Select::make('parent_id')
                ->label('Parent (opsional)')
                ->reactive()
                ->options(function (Get $get, ?\App\Models\Lampiran $record) {
                    $berkasId = $get('berkas_id') ?? request('berkas_id');
                    return \App\Models\Lampiran::query()
                        ->when($berkasId, fn($q) => $q->where('berkas_id', $berkasId))
                        ->when($record, fn($q) => $q->where('id', '!=', $record->id)) // tidak boleh jadi parent dirinya sendiri
                        ->orderBy('nama')
                        ->pluck('nama', 'id');
                })
                ->searchable()
                ->nullable()
                ->default(request('parent_id')),

            Forms\Components\TextInput::make('nama')->required()->maxLength(255),

            Forms\Components\FileUpload::make('file')
                ->disk('public')->directory('lampiran')
                ->visibility('public')
                ->preserveFilenames()
                ->downloadable()
                ->rules(['nullable', 'file'])
                ->previewable(),

            Forms\Components\TagsInput::make('keywords')
                ->label('Kata Kunci')
                ->placeholder('New tag')
                ->afterStateHydrated(function (Forms\Components\TagsInput $component, $state) {
                    $parse = function ($v) {
                        if (blank($v)) return [];
                        if (is_array($v)) return array_values(array_filter($v));

                        if (is_string($v)) {
                            $j = json_decode($v, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
                                return array_values(array_filter($j));
                            }
                            // CSV "a, b, c"
                            return array_values(array_filter(
                                preg_split('/\s*,\s*/', $v, -1, PREG_SPLIT_NO_EMPTY)
                            ));
                        }
                        return array_values(array_filter((array) $v));
                    };

                    $component->state($parse($state));
                })
                ->dehydrateStateUsing(fn ($state) =>
                    array_values(array_unique(array_filter(array_map('trim', (array) $state))))
                )
                ->reorderable()
                ->separator(','),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('berkas.nama')
                    ->label('Berkas')
                    ->sortable()
                    // ->extraCellAttributes(['class' => 'max-w-[20rem] whitespace-normal break-words'])
                    ->toggleable(),

                // PENCARIAN: nama + keywords (dalam satu kolom saja)
                Tables\Columns\TextColumn::make('nama')
                    ->label('Lampiran')
                    ->extraCellAttributes(['class' => 'max-w-[60rem] whitespace-normal break-words'])
                    ->sortable()
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            $like = "%{$search}%";
                            $likeLower = '%' . mb_strtolower($search) . '%';

                            return $query->where(function (Builder $q) use ($like, $likeLower) {
                                $q->where('lampirans.nama', 'like', $like)
                                ->orWhereRaw('LOWER(CAST(lampirans.keywords AS CHAR)) LIKE ?', [$likeLower]);
                            });
                        }
                    ),

                Tables\Columns\TextColumn::make('parent.nama')
                    ->label('Parent')
                    ->wrap() // <-- penting: hilangkan nowrap, biar bisa turun baris
                    ->extraCellAttributes([
                        'class' => 'max-w-[20rem] whitespace-normal break-words', 
                        // boleh sesuaikan 20rem (320px)
                    ])
                    ->toggleable(),

                Tables\Columns\ViewColumn::make('keywords_view')
                    ->label('Kata Kunci')
                    ->state(function (\App\Models\Lampiran $record) {
                        // Ambil nilai mentah dari DB (bisa json string / array / csv)
                        $raw = $record->getRawOriginal('keywords');

                        // Jadikan array awal
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

                        // Pecah juga jika di dalam array masih ada item berbentuk CSV
                        $tokens = collect($arr)
                            ->flatMap(function ($item) {
                                if (is_array($item)) return $item;
                                $s = trim((string) $item, " \t\n\r\0\x0B\"'"); // buang spasi & kutip
                                // kalau ada koma → pecah; kalau tidak, jadikan satu elemen
                                return preg_split('/\s*,\s*/u', $s, -1, PREG_SPLIT_NO_EMPTY);
                            })
                            ->map(fn ($s) => trim((string) $s))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();

                        return $tokens; // <- keywords-grid akan render per chip
                    })
                    ->view('tables.columns.keywords-grid')
                    ->extraCellAttributes(['class' => 'max-w-[20rem] whitespace-normal break-words'])
                    ->toggleable(),
            ])
            ->filters([ //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('addChild')
                    ->label('Tambah Sub')
                    ->icon('heroicon-m-plus')
                    ->url(fn ($record) => route('filament.admin.resources.lampirans.create', [
                        'parent_id' => $record->id,
                        'berkas_id' => $record->berkas_id,
                    ])),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLampirans::route('/'),
            'create' => Pages\CreateLampiran::route('/create'),
            'edit' => Pages\EditLampiran::route('/{record}/edit'),
        ];
    }
}
