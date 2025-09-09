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
use Filament\Forms\Components\Actions\Action as FormAction;
use App\Models\Berkas;
use Filament\Tables\Actions\ViewAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\View as ViewField;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

class LampiranResource extends Resource
{
    // protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Lampiran::class;

    protected static ?string $navigationIcon = 'heroicon-m-paper-clip';

    protected static ?string $navigationLabel = 'Dokumen Pelengkap';

    protected static ?string $modelLabel = 'lampiran';
    protected static ?string $pluralModelLabel = 'Dokumen Pelengkap';

    public static function form(Form $form): Form
    {
        $readonly = fn (string $context = null) =>
        ($context === 'view') || request()->boolean('view'); // ← dukung context & ?view=1
        
        return $form->schema([
            Forms\Components\Select::make('berkas_id')
                ->label('Dokumen')
                ->relationship('berkas', 'nama')
                ->searchable()
                ->preload()
                ->required()
                ->disabled($readonly)
                ->default(request('berkas_id')),

            Forms\Components\Select::make('parent_id')
                ->label('Parent (opsional)')
                ->reactive()
                ->disabled($readonly)
                ->options(function (Get $get, ?\App\Models\Lampiran $record) {
                    $berkasId = $get('berkas_id') ?? request('berkas_id');
                    return \App\Models\Lampiran::query()
                        ->when($berkasId, fn($q) => $q->where('berkas_id', $berkasId))
                        ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                        ->orderBy('nama')
                        ->pluck('nama', 'id');
                })
                ->searchable()
                ->nullable()
                ->default(request('parent_id')),

            Forms\Components\TextInput::make('nama')
                ->required()
                ->disabled($readonly)
                ->maxLength(255),

            // ===== File Lampiran (PRIVATE) =====
            Forms\Components\FileUpload::make('file')
                ->disk('private')
                ->disabled($readonly)
                ->directory('lampiran')
                ->preserveFilenames()
                ->rules(['nullable', 'file'])
                ->previewable(true)
                ->downloadable(false) // JANGAN generate URL publik
                ->openable(false)     // JANGAN open via Storage::url
                ->hintAction(
                    FormAction::make('openFile')
                        ->label('Buka file')
                        ->url(
                            fn ($record) => ($record && $record->file)
                                ? route('media.berkas.lampiran', [
                                    'berkas'  => $record->berkas_id,
                                    'lampiran'=> $record->id,
                                ])
                                : null,
                            shouldOpenInNewTab: true
                        )
                        ->visible(fn ($record) => filled($record?->file))
                ),

            Forms\Components\TagsInput::make('keywords')
                ->label('Kata Kunci')
                ->placeholder('New tag')
                ->disabled($readonly)
                ->afterStateHydrated(function (Forms\Components\TagsInput $component, $state) {
                    $parse = function ($v) {
                        if (blank($v)) return [];
                        if (is_array($v)) return array_values(array_filter($v));

                        if (is_string($v)) {
                            $j = json_decode($v, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
                                return array_values(array_filter($j));
                            }
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

                Section::make('Riwayat lampiran')
                    ->visible(fn (string $context) => $context === 'view')   // <- pakai context, bukan request()
                    ->schema([
                        \Filament\Forms\Components\View::make('lampiran_history')
                            ->view('tables.rows.lampiran-history')
                            ->columnSpanFull(),
                    ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->leftJoin('berkas as b', 'lampirans.berkas_id', '=', 'b.id')
                    ->select('lampirans.*');
            })
            ->columns([
                Tables\Columns\TextColumn::make('berkas.nama')
                    ->label('Part Name')
                    // sort TANPA join lagi (kita sudah join alias `b` di base query)
                    ->sortable(query: fn (Builder $query, string $direction): Builder =>
                        $query->orderBy('b.nama', $direction)
                    )
                    // searchable juga TANPA join lagi, pakai nama param `search`
                    ->searchable(query: fn (Builder $query, string $search): Builder =>
                        $query->where('b.nama', 'like', "%{$search}%")
                    )
                    ->toggleable(),

                // PENCARIAN: nama + keywords (dalam satu kolom saja)
                Tables\Columns\TextColumn::make('nama')
                    ->label('Lampiran')
                    ->extraCellAttributes(['class' => 'max-w-[60rem] whitespace-normal break-words'])
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $table = $query->getModel()->getTable(); // 'lampirans'
                        return $query
                            // bersihkan ORDER BY lama, lalu tetap kunci Part Name A–Z
                            ->reorder('b.nama', 'asc')
                            // urut angka pertama dalam 'nama' sebagai numerik
                            ->orderByRaw(
                                "IFNULL(CAST(REGEXP_SUBSTR({$table}.nama, '[0-9]+') AS UNSIGNED), 999999999) {$direction}"
                            )
                            // fallback alfabet jika sama angkanya
                            ->orderBy("{$table}.nama", $direction);
                    })
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
                ViewAction::make()
                    ->label('')
                    ->icon('heroicon-m-eye')
                    ->tooltip('Lihat')
                    ->url(fn () => null)                 // <- paksa modal, jangan ke page /view
                    ->modalHeading('Lihat Lampiran')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                Tables\Actions\EditAction::make()
                    ->visible(fn()=>auth()->user()->can('lampiran.update')),

                Tables\Actions\Action::make('addChild')
                    ->label('Tambah Sub')
                    ->icon('heroicon-m-plus')
                    ->url(fn ($record) => route('filament.admin.resources.lampirans.create', [
                        'parent_id' => $record->id,
                        'berkas_id' => $record->berkas_id,
                    ]))
                    ->visible(fn()=>auth()->user()->can('lampiran.create')),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn()=>auth()->user()->can('lampiran.delete')),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Cukup eager-load relasi. TANPA join/order.
        return parent::getEloquentQuery()->with(['berkas', 'parent']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLampirans::route('/'),
            'create' => Pages\CreateLampiran::route('/create'),
            'edit' => Pages\EditLampiran::route('/{record}/edit'),
            'view' => Pages\ViewLampiran::route('/{record}/view'), 
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('lampiran.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('lampiran.create') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('lampiran.view') ?? false;
    }

}
