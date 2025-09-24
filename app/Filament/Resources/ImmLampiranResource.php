<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ImmLampiranResource\Pages;
use App\Models\ImmLampiran;
use Filament\Forms;
use Filament\Forms\Components\{Hidden, Group};
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Forms\Components\{Select, TextInput, TagsInput, FileUpload, Section, View as ViewField};
use Filament\Forms\Get;
use Filament\Forms\Components\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Filament\Forms\Components\DatePicker;

class ImmLampiranResource extends Resource
{
    protected static ?string $model = ImmLampiran::class;
    public static function getNavigationLabel(): string { return 'IMM Lampiran'; }
    public static function shouldRegisterNavigation(): bool { return false; } // sembunyikan

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([

                // tetap
                Hidden::make('documentable_type')
                    ->default(fn (?ImmLampiran $record) =>
                        $record?->documentable_type
                        ?? request('documentable_type')
                        ?? request('doc_type')
                    ),

                // === DOKUMEN (dropdown) ===
                Select::make('documentable_id')
                    ->label('Dokumen')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => request('documentable_id') ?? request('doc_id'))
                    ->options(function (Get $get, ?ImmLampiran $record) {
                        $type = $record?->documentable_type
                            ?: $get('documentable_type')
                            ?: request('documentable_type')
                            ?: request('doc_type');

                        $map = [
                            'ImmManualMutu'       => \App\Models\ImmManualMutu::class,
                            'ImmProsedur'         => \App\Models\ImmProsedur::class,
                            'ImmInstruksiStandar' => \App\Models\ImmInstruksiStandar::class,
                            'ImmFormulir'         => \App\Models\ImmFormulir::class,
                            'ImmAuditInternal'    => \App\Models\ImmAuditInternal::class,
                        ];
                        $cls = class_exists($type) ? $type : ($map[$type] ?? null);
                        if (! $cls) return [];

                        $table = (new $cls)->getTable();
                        $labelCol = collect(['nama_dokumen','nama','title','name'])
                            ->first(fn ($c) => Schema::hasColumn($table, $c));

                        $q = $cls::query();
                        if ($labelCol) $q->orderBy($labelCol,'asc');

                        return $q->get()->mapWithKeys(function ($m) {
                            $label = $m->nama_dokumen ?? $m->nama ?? $m->title ?? $m->name ?? ('#'.$m->getKey());
                            return [$m->getKey() => $label];
                        })->all();
                    })
                    ->disabledOn('view')
                    // 🔒: saat EDIT & bukan Admin → kunci & jangan dehydrated
                    ->disabled(fn (?ImmLampiran $record) => $record && ! (auth()->user()?->hasRole('Admin') ?? false))
                    ->dehydrated(fn (?ImmLampiran $record) => ! ($record && ! (auth()->user()?->hasRole('Admin') ?? false))),

                // === PARENT (opsional) – hanya lampiran milik dokumen yang dipilih ===
                Select::make('parent_id')
                    ->label('Parent (opsional)')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->default(fn () => request('parent_id'))
                    ->disabledOn('view')
                    ->reactive()
                    ->options(function (Get $get, ?ImmLampiran $record) {
                        $type = $record?->documentable_type
                            ?: $get('documentable_type')
                            ?: request('documentable_type')
                            ?: request('doc_type');

                        $docId = $record?->documentable_id
                            ?: $get('documentable_id')
                            ?: request('documentable_id')
                            ?: request('doc_id');

                        $map = [
                            'ImmManualMutu'       => \App\Models\ImmManualMutu::class,
                            'ImmProsedur'         => \App\Models\ImmProsedur::class,
                            'ImmInstruksiStandar' => \App\Models\ImmInstruksiStandar::class,
                            'ImmFormulir'         => \App\Models\ImmFormulir::class,
                            'ImmAuditInternal'    => \App\Models\ImmAuditInternal::class,
                        ];
                        $cls = class_exists($type) ? $type : ($map[$type] ?? null);

                        return \App\Models\ImmLampiran::query()
                            ->when($cls && $docId, fn ($q) => $q
                                ->where('documentable_type', $cls)
                                ->where('documentable_id', $docId)
                            )
                            ->when($record, fn ($q) => $q->where('id','!=',$record->id))
                            ->orderBy('nama')
                            ->pluck('nama','id');
                    })
                    // 🔒
                    ->disabled(fn (?ImmLampiran $record) => $record && ! (auth()->user()?->hasRole('Admin') ?? false))
                    ->dehydrated(fn (?ImmLampiran $record) => ! ($record && ! (auth()->user()?->hasRole('Admin') ?? false))),

                // === Field utama ===
                TextInput::make('nama')
                    ->label('Nama Lampiran')
                    ->required()
                    ->disabledOn('view')
                    // 🔒
                    ->disabled(fn (?ImmLampiran $record) => $record && ! (auth()->user()?->hasRole('Admin') ?? false))
                    ->dehydrated(fn (?ImmLampiran $record) => ! ($record && ! (auth()->user()?->hasRole('Admin') ?? false))),

                TagsInput::make('keywords')
                    ->label('Kata Kunci')
                    ->separator(',')
                    ->reorderable()
                    ->disabledOn('view')
                    // 🔒
                    ->disabled(fn (?ImmLampiran $record) => $record && ! (auth()->user()?->hasRole('Admin') ?? false))
                    ->dehydrated(fn (?ImmLampiran $record) => ! ($record && ! (auth()->user()?->hasRole('Admin') ?? false))),

                DatePicker::make('deadline_at')
                    ->label('Deadline Upload')
                    ->native(false)
                    ->displayFormat('d/M/Y')
                    ->closeOnDateSelection()
                    ->disabledOn('view')
                    ->helperText('Batas waktu departemen mengunggah file')
                    ->visible(fn ($get) => str_contains((string) $get('documentable_type'), 'ImmAuditInternal'))
                    ->nullable()
                    // 🔒
                    ->disabled(fn (?ImmLampiran $record) => $record && ! (auth()->user()?->hasRole('Admin') ?? false))
                    ->dehydrated(fn (?ImmLampiran $record) => ! ($record && ! (auth()->user()?->hasRole('Admin') ?? false))),

                // === FILE UTAMA → tetap bisa diubah oleh Editor/Departemen
                FileUpload::make('file')
                    ->disk('private')
                    ->directory('imm/lampiran')
                    ->previewable(true)
                    ->openable(false)
                    ->disabledOn('view')
                    ->downloadable(false)
                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file) {
                        $disk = 'private';
                        $dir  = 'imm/lampiran';
                        $orig = $file->getClientOriginalName();
                        $name = pathinfo($orig, PATHINFO_FILENAME);
                        $ext  = $file->getClientOriginalExtension();

                        $candidate = $orig; $i = 1;
                        while (\Storage::disk($disk)->exists("$dir/$candidate")) {
                            $candidate = "{$name} ({$i}).{$ext}";
                            $i++;
                        }
                        return $file->storeAs($dir, $candidate, $disk);
                    })
                    ->hintAction(
                        FormAction::make('openFile')
                            ->label('Buka file')
                            ->url(fn ($record) => $record?->file
                                ? route('media.imm.lampiran', ['lampiran' => $record->id])
                                : null,
                                shouldOpenInNewTab: true
                            )
                            ->visible(fn ($record) => filled($record?->file))
                    ),

                // === FILE ASLI (Admin saja) – tetap seperti sebelumnya
                FileUpload::make('file_src')
                    ->label('File Asli (Admin saja)')
                    ->disk('private')
                    ->directory('imm/lampiran/_source')
                    ->preserveFilenames()
                    ->rules(['nullable','file'])
                    ->previewable(true)
                    ->downloadable(false)
                    ->openable(false)
                    ->disabledOn('view')
                    ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false)
                    ->helperText('Hanya Admin yang dapat mengganti file asli'),
            ])->columns(2),

            Section::make('Riwayat lampiran')
                ->visibleOn('view')
                ->schema([
                    ViewField::make('imm_history')
                        ->view('tables.rows.imm-lampiran-history')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListImmLampiran::route('/'),
            'create' => Pages\CreateImmLampiran::route('/create'),
            'edit'   => Pages\EditImmLampiran::route('/{record}/edit'),
        ];
    }
}
