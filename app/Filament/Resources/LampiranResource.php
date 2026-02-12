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
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;
use App\Filament\Support\RowClickViewForNonEditors;
use App\Filament\Support\FileCell;
use App\Models\Lampiran as MLampiran;
use App\Models\EventCustomer;

class LampiranResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false; 
    protected static bool $isGloballySearchable   = false;

    protected static ?string $model = Lampiran::class;

    protected static ?string $navigationIcon = 'heroicon-m-paper-clip';

    protected static ?string $navigationLabel = 'Dokumen Pelengkap';

    protected static ?string $modelLabel = 'lampiran';
    protected static ?string $pluralModelLabel = 'Dokumen Pelengkap';

    use RowClickViewForNonEditors, FileCell;

    public static function form(Form $form): Form
    {
        $readonly = fn (string $context = null) =>
        ($context === 'view') || request()->boolean('view');

        return $form->schema([
            // === Event (berkas_id) ===
            Forms\Components\Select::make('berkas_id')
                ->label('Event / Project')
                ->options(function () {
                    $berkas = \App\Models\Berkas::query()
                        ->pluck('nama', 'id')
                        ->toArray();

                    $eventCustomer = \App\Models\EventCustomer::query()
                        ->pluck('nama', 'id') 
                        ->toArray(); 

                    return $berkas + $eventCustomer;
                })
                ->getOptionLabelUsing(function ($value) {
                    $berkas = \App\Models\Berkas::find($value);
                    if ($berkas) return $berkas->nama;

                    $ec = \App\Models\EventCustomer::find($value);
                    if ($ec) return $ec->nama; 

                    return $value; 
                })
                ->searchable()
                ->preload()
                ->live()
                ->default(request('berkas_id'))
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $set('regular_id', null);
                    $set('parent_id', null);
                    $set('nama', null);
                    
                    if ($state) {
                        $entity = \App\Models\Berkas::with('customer')->find($state) 
                            ?? \App\Models\EventCustomer::with('customer')->find($state);
                        
                        if ($entity) {
                            $customerName = filled($entity->cust_name) 
                                ? $entity->cust_name 
                                : $entity->customer?->name;

                            $set('customer_display', $customerName); 
                        } else {
                            $set('customer_display', null);
                        }
                    } else {
                        $set('customer_display', null);
                    }
                })
                ->hidden(fn (Forms\Get $get) => filled($get('regular_id')) || filled(request('regular_id')))
                ->dehydrated(fn (Forms\Get $get) => filled($get('berkas_id')))
                ->required(fn (Forms\Get $get) => blank($get('regular_id')))
                ->disabled($readonly),

            Forms\Components\TextInput::make('customer_display')
                ->label('Customer')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(function (Forms\Get $get, ?\App\Models\Lampiran $record) {
                    $berkasId = $get('berkas_id');
                    $regularId = $get('regular_id');

                    if (blank($berkasId) && blank($regularId)) {
                        $berkasId = request('berkas_id');
                        $regularId = request('regular_id');
                    }
                    if (blank($berkasId) && blank($regularId) && $record) {
                        $berkasId = $record->berkas_id;
                        $regularId = $record->regular_id;
                    }

                    if ($berkasId) {
                        $entity = Berkas::with('customer')->find($berkasId) 
                            ?? EventCustomer::with('customer')->find($berkasId);

                        if (!$entity) return null;

                        return filled($entity->cust_name)
                            ? $entity->cust_name
                            : $entity->customer?->name;
                    }

                    if ($regularId) {
                        $regular = \App\Models\Regular::with('customer')->find($regularId);
                        if (!$regular) return null;
                        return filled($regular->cust_name) ? $regular->cust_name : $regular->customer?->name;
                    }

                    return null;
                }),
                
            Forms\Components\Select::make('regular_id')
                ->label('Regular')
                ->relationship('regular', 'nama')
                ->searchable()->preload()->live()
                ->default(request('regular_id'))
                ->hidden(fn (Forms\Get $get) => filled($get('berkas_id')) || filled(request('berkas_id')))
                ->dehydrated(fn (Forms\Get $get) => filled($get('regular_id')))
                ->required(fn (Forms\Get $get) => blank($get('berkas_id')))
                ->disabled($readonly)
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $set('berkas_id', null);
                    $set('parent_id', null);
                    $set('nama', null);
                    
                    if ($state) {
                        $regular = \App\Models\Regular::with('customer')->find($state);
                        
                        $customerName = filled($regular?->cust_name) 
                            ? $regular->cust_name 
                            : $regular?->customer?->name;
                        
                        $set('customer_display', $customerName);
                    } else {
                        $set('customer_display', null);
                    }
                }),

            Forms\Components\Select::make('parent_id')
                ->label('Parent (opsional)')
                ->reactive()
                ->disabled($readonly)
                ->options(function (Get $get, ?\App\Models\Lampiran $record) {
                        $berkasId  = $get('berkas_id')  ?? request('berkas_id');
                        $regularId = $get('regular_id') ?? request('regular_id');

                        return \App\Models\Lampiran::query()
                            ->when($berkasId,  fn($q) => $q->where('berkas_id',  $berkasId))
                            ->when($regularId, fn($q) => $q->where('regular_id', $regularId))
                            ->when(!$berkasId && !$regularId, fn($q) => $q->whereRaw('1=0'))
                            ->when($record,    fn($q) => $q->where('id', '!=', $record->id))
                            ->orderBy('nama')
                            ->pluck('nama', 'id');
                    })
                ->searchable()
                ->nullable()
                ->default(request('parent_id')),

            Forms\Components\Select::make('nama')
                ->label('Nama Dokumen')
                ->placeholder('Pilih atau ketik nama dokumen...')
                ->required()
                ->searchable()
                ->preload()
                ->live() 
                ->options(function (Forms\Get $get) {
                    $berkasId = $get('berkas_id');
                    if (! $berkasId) return [];

                    $entity = Berkas::find($berkasId) ?? EventCustomer::find($berkasId);
                    
                    if (! $entity) return [];

                    $customer = $entity->customer;

                    if (! $customer && ! empty($entity->cust_name)) {
                        $customer = \App\Models\Customer::where('name', $entity->cust_name)->first();
                    }

                    if (! $customer || empty($customer->document_templates)) {
                        return [];
                    }

                    return collect($customer->document_templates)
                        ->pluck('name', 'name')
                        ->toArray();
                })
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Dokumen Baru')
                        ->required(),
                ])
                ->createOptionUsing(function (array $data, Forms\Get $get) {
                    $newDocName = $data['name'];
                    $berkasId = $get('berkas_id');

                    // 1. Pastikan ada Berkas/Event yang dipilih
                    if ($berkasId) {
                        $berkas = \App\Models\Berkas::find($berkasId);

                        // 2. Cari Customer berdasarkan cust_name dari Berkas tersebut
                        if ($berkas && $berkas->cust_name) {
                            $customer = \App\Models\Customer::where('name', $berkas->cust_name)->first();

                            if ($customer) {
                                // 3. Ambil data template lama (handle jika null)
                                $templates = $customer->document_templates ?? [];

                                // 4. Cek apakah nama ini sudah ada (biar gak duplikat)
                                $exists = collect($templates)->contains('name', $newDocName);

                                if (! $exists) {
                                    // 5. Tambahkan nama baru ke array dengan format Key: name
                                    $templates[] = ['name' => $newDocName];

                                    // 6. UPDATE ke database Customer
                                    $customer->update(['document_templates' => $templates]);

                                    // (Opsional) Kirim notifikasi kecil
                                    \Filament\Notifications\Notification::make()
                                        ->title('Disimpan ke Template Customer')
                                        ->success()
                                        ->send();
                                }
                            }
                        }
                    }

                    // Return nama dokumen agar terpilih di field Select
                    return $newDocName;
                }),

            Forms\Components\TagsInput::make('keywords')
                ->label('Kata Kunci')
                ->placeholder('New tag')
                ->disabled($readonly)
                ->separator(','), // Kode tags input disederhanakan tampilannya di sini

            // ===== File Lampiran (PRIVATE) =====
            Forms\Components\FileUpload::make('file')
                ->disk('private')
                ->disabled($readonly)
                ->directory('lampiran')
                ->preserveFilenames()
                ->rules(['nullable', 'file'])
                ->previewable(true)
                ->downloadable(false)
                ->openable(false)
                ->hintAction(
                    FormAction::make('openFile')
                        ->label('Buka file')
                        ->url(fn ($record) => $record?->mediaUrl(), true)
                        ->visible(fn ($record) =>
                            filled($record?->file) &&
                            (auth()->user()?->hasAnyRole(['Admin','Editor','Staff']) ?? false)
                        )
                ),

            FileUpload::make('file_src')
                ->label('File Asli (Admin saja)')
                ->disk('private')
                ->directory('lampiran/_source')
                ->preserveFilenames()
                ->disabled($readonly)
                ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false),
            
            Section::make('Riwayat lampiran')
            ->visible(fn (string $context) => $context === 'view')
            ->schema([
                \Filament\Forms\Components\View::make('lampiran_history')
                    ->view('tables.rows.lampiran-history')
                    ->columnSpanFull(),
            ])
            ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query
                    ->leftJoin('berkas as b', 'lampirans.berkas_id', '=', 'b.id')
                    ->leftJoin('event_customers as ec', 'lampirans.berkas_id', '=', 'ec.id') 
                    ->leftJoin('regulars as r', 'lampirans.regular_id', '=', 'r.id')
                    ->selectRaw('lampirans.*, COALESCE(b.nama, ec.part_name, ec.nama, r.nama) as owner_name'); 
            })
            ->columns([
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Owner')
                    ->sortable(query: function (Builder $q, string $dir): Builder {
                        $table = $q->getModel()->getTable();
                        return $q
                            ->reorder()
                            ->orderByRaw("COALESCE(b.nama, ec.part_name, ec.nama, r.nama) {$dir}") 
                            ->orderByRaw("IFNULL(CAST(REGEXP_SUBSTR({$table}.nama, '[0-9]+') AS UNSIGNED), 999999999) {$dir}")
                            ->orderBy("{$table}.nama", $dir);
                    })
                    ->searchable(query: fn (Builder $q, string $search) =>
                        $q->where(function (Builder $w) use ($search) {
                            $w->where('b.nama', 'like', "%{$search}%")
                            ->orWhere('ec.part_name', 'like', "%{$search}%") 
                            ->orWhere('ec.nama', 'like', "%{$search}%") // Jaga-jaga jika kolomnya nama
                            ->orWhere('r.nama', 'like', "%{$search}%");
                        })
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('nama')
                    ->label('Lampiran')
                    ->extraCellAttributes(['class' => 'max-w-[60rem] whitespace-normal break-words'])
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $table = $query->getModel()->getTable(); // 'lampirans'
                        return $query
                            ->reorder() 
                            ->orderByRaw("COALESCE(b.nama, r.nama) asc") 
                            ->orderByRaw("IFNULL(CAST(REGEXP_SUBSTR({$table}.nama, '[0-9]+') AS UNSIGNED), 999999999) {$direction}")
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
                    ->modalHeading('Lihat Lampiran')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('7xl'),

                Tables\Actions\EditAction::make()
                    ->visible(fn()=>auth()->user()->can('lampiran.update')),

                Tables\Actions\Action::make('addChild')
                    ->label('Tambah Sub')
                    ->icon('heroicon-m-plus')
                    ->url(fn ($record) => route('filament.admin.resources.lampirans.create', array_filter([
                        'parent_id'  => $record->id,
                        $record->berkas_id ? 'berkas_id' : 'regular_id' => $record->berkas_id ?: $record->regular_id,
                    ])))
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
        return parent::getEloquentQuery()->with(['berkas', 'regular', 'parent']);
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

    public static function normalizeOwner(array $data): array
    {
        $berkasId  = request()->integer('berkas_id')  ?: ($data['berkas_id']  ?? null);
        $regularId = request()->integer('regular_id') ?: ($data['regular_id'] ?? null);

        // Pastikan eksklusif satu owner
        if ($berkasId && $regularId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'owner' => 'Pilih salah satu: Event/Berkas atau Regular.',
            ]);
        }
        if (!$berkasId && !$regularId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'owner' => 'Lampiran harus terikat ke Event/Berkas atau Regular.',
            ]);
        }

        $data['berkas_id']  = $berkasId ?: null;
        $data['regular_id'] = $regularId ?: null;

        return $data;
    }

}
