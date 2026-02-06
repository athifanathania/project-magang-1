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
        // helper sekali, dipakai ulang di semua closure
        $isAuditLocked = fn (?ImmLampiran $record) =>
            $record
            && str_contains(ltrim((string)($record->documentable_type ?? ''), '\\'), 'ImmAuditInternal')
            && ! (auth()->user()?->hasRole('Admin') ?? false);

        return $form->schema([
            Section::make()->schema([

                Hidden::make('documentable_type')
                    ->default(fn (?ImmLampiran $record) =>
                        $record?->documentable_type
                        ?? request('documentable_type')
                        ?? request('doc_type')
                    ),

                // Dokumen / Departemen
                Select::make('documentable_id')
                    ->label(fn (Get $get, ?ImmLampiran $record) =>
                        ($record && str_contains(ltrim((string)($record->documentable_type ?? ''), '\\'), 'ImmAuditInternal'))
                            ? 'Departemen'
                            : 'Dokumen'
                    )
                    ->required()->searchable()->preload()
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

                        $table   = (new $cls)->getTable();
                        $labelCol= collect(['departemen','nama_dokumen','nama','title','name'])
                            ->first(fn ($c) => \Illuminate\Support\Facades\Schema::hasColumn($table, $c));

                        $q = $cls::query();
                        if ($labelCol) $q->orderBy($labelCol, 'asc');

                        return $q->get()->mapWithKeys(function ($m) {
                            $label = $m->departemen ?? $m->nama_dokumen ?? $m->nama ?? $m->title ?? $m->name ?? ('#'.$m->getKey());
                            return [$m->getKey() => $label];
                        })->all();
                    })
                    ->getOptionLabelUsing(function ($value, Get $get, ?ImmLampiran $record) {
                        if (! $value) return null;
                        $type = $record?->documentable_type
                            ?: $get('documentable_type')
                            ?: request('documentable_type')
                            ?: request('doc_type');

                        $map = [
                            'ImmManualMutu' => \App\Models\ImmManualMutu::class,
                            'ImmProsedur'   => \App\Models\ImmProsedur::class,
                            'ImmInstruksiStandar' => \App\Models\ImmInstruksiStandar::class,
                            'ImmFormulir'   => \App\Models\ImmFormulir::class,
                            'ImmAuditInternal' => \App\Models\ImmAuditInternal::class,
                        ];
                        $cls = class_exists($type) ? $type : ($map[$type] ?? null);
                        if (! $cls) return '#'.$value;

                        $m = $cls::find($value);
                        return $m?->departemen ?? $m?->nama_dokumen ?? $m?->nama ?? $m?->title ?? $m?->name ?? ('#'.$value);
                    })
                    ->disabled(fn (?ImmLampiran $record, string $operation) =>
                        $operation === 'view' || $isAuditLocked($record)
                    )
                    ->dehydrated(fn (?ImmLampiran $record, string $operation) =>
                        $operation !== 'view' && ! $isAuditLocked($record)
                    ),

                // Parent
                Select::make('parent_id')
                    ->label(fn (Get $get, ?ImmLampiran $record) =>
                        ($record && str_contains(ltrim((string)($record->documentable_type ?? ''), '\\'), 'ImmAuditInternal'))
                            ? 'Parent Temuan (opsional)'
                            : 'Parent (opsional)'
                    )
                    ->searchable()->preload()->nullable()
                    ->default(fn () => request('parent_id'))
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
                            ->orderBy('nama')
                            ->pluck('nama', 'id');
                    })
                    ->getOptionLabelUsing(fn ($value) =>
                        optional(\App\Models\ImmLampiran::find($value))->nama ?? ('#'.$value)
                    )
                    ->disabled(fn (?ImmLampiran $record, string $operation) =>
                        $operation === 'view' || $isAuditLocked($record)
                    )
                    ->dehydrated(fn (?ImmLampiran $record, string $operation) =>
                        $operation !== 'view' && ! $isAuditLocked($record)
                    ),

                // Nama
                TextInput::make('nama')
                    ->label('Nama Lampiran')->required()
                    ->disabled(fn (?ImmLampiran $record, string $operation) =>
                        $operation === 'view' || $isAuditLocked($record)
                    )
                    ->dehydrated(fn (?ImmLampiran $record, string $operation) =>
                        $operation !== 'view' && ! $isAuditLocked($record)
                    ),

                // Kata Kunci
                TagsInput::make('keywords')
                    ->label('Kata Kunci')->separator(',')->reorderable()
                    ->disabled(fn (?ImmLampiran $record, string $operation) =>
                        $operation === 'view' || $isAuditLocked($record)
                    )
                    ->dehydrated(fn (?ImmLampiran $record, string $operation) =>
                        $operation !== 'view' && ! $isAuditLocked($record)
                    ),

                // Deadline (khusus Audit)
                DatePicker::make('deadline_at')
                    ->label('Deadline Upload')
                    ->native(false)
                    ->displayFormat('d/M/Y')
                    ->closeOnDateSelection()
                    ->helperText('Batas waktu departemen mengunggah file')
                    ->visible(function (Get $get, ?ImmLampiran $record) {
                        $type = $record?->documentable_type
                            ?: $get('documentable_type')
                            ?: request('documentable_type')
                            ?: request('doc_type');

                        $type = ltrim((string) $type, '\\');
                        $short = Str::afterLast($type, '\\');   // handle FQCN "App\Models\ImmAuditInternal"
                        return $short === 'ImmAuditInternal';
                    })
                    ->nullable()
                    ->disabled(fn (?ImmLampiran $record, string $operation) =>
                        $operation === 'view' || (
                            $record
                            && Str::afterLast(ltrim((string)($record->documentable_type ?? ''), '\\'), '\\') === 'ImmAuditInternal'
                            && ! (auth()->user()?->hasRole('Admin') ?? false)
                        )
                    )
                    ->dehydrated(fn (?ImmLampiran $record, string $operation) =>
                        $operation !== 'view'
                    ),

                // File utama
                FileUpload::make('file')
                    // MODIFIKASI: Label dinamis
                    ->label(fn (Get $get, ?ImmLampiran $record) => 
                        str_contains(ltrim((string)($record?->documentable_type ?? $get('documentable_type') ?? request('documentable_type') ?? request('doc_type') ?? ''), '\\'), 'ImmAuditInternal')
                        ? 'File Record' 
                        : 'File Record (Admin/Editor)'
                    )
                    ->disk('private')
                    ->directory('imm/lampiran')
                    ->previewable(true)
                    ->openable(false)
                    ->visible(fn () => auth()->user()->hasAnyRole(['Admin', 'Editor']))
                    ->saveUploadedFileUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file) {
                        $disk = 'private'; $dir = 'imm/lampiran';
                        $orig = $file->getClientOriginalName();
                        $name = pathinfo($orig, PATHINFO_FILENAME);
                        $ext  = $file->getClientOriginalExtension();
                        $candidate = $orig; $i = 1;
                        while (\Storage::disk($disk)->exists("$dir/$candidate")) {
                            $candidate = "{$name} ({$i}).{$ext}"; $i++;
                        }
                        return $file->storeAs($dir, $candidate, $disk);
                    })
                    ->hintAction(
                        \Filament\Forms\Components\Actions\Action::make('openFile')
                            ->label('Buka file')
                            ->url(fn ($record) => $record?->file
                                ? route('media.imm.lampiran', ['lampiran' => $record->id])
                                : null,
                                shouldOpenInNewTab: true
                            )
                            ->visible(fn ($record) =>
                                filled($record?->file)
                                && (auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)
                            )
                    )
                    ->disabledOn('view'),

                // File Staff - MODIFIKASI VISIBILITY
                FileUpload::make('file_staf')
                    ->label('File Download Staf')
                    ->disk('private')
                    ->directory('imm/lampiran/staf')
                    ->previewable(true)
                    ->openable(true)
                    ->downloadable(true)
                    ->rules(['nullable', 'file'])
                    // MODIFIKASI: Sembunyikan jika tipe dokumen adalah Audit Internal
                    ->visible(fn (Get $get, ?ImmLampiran $record) => 
                        ! str_contains(ltrim((string)($record?->documentable_type ?? $get('documentable_type') ?? request('documentable_type') ?? request('doc_type') ?? ''), '\\'), 'ImmAuditInternal')
                    )
                    ->disabled(fn (string $operation) => 
                        $operation === 'view' || (auth()->user()?->hasRole('Staff') ?? false)
                    )
                    ->deletable(fn (string $operation) => 
                        $operation !== 'view' && !(auth()->user()?->hasRole('Staff') ?? false)
                    )
                    ->hintAction(
                        \Filament\Forms\Components\Actions\Action::make('buka_file_staf')
                            ->label('Buka File')
                            ->icon('heroicon-m-arrow-top-right-on-square')
                            ->url(fn ($record) => $record?->file_staf ? route('media.imm.lampiran', ['lampiran' => $record->id, 'type' => 'staf']) : null, shouldOpenInNewTab: true)
                            ->visible(fn ($record) => filled($record?->file_staf))
                    ),

                // File asli (Admin saja)
                FileUpload::make('file_src')
                    ->label('File Asli (Admin saja)')
                    ->disk('private')->directory('imm/lampiran/_source')
                    ->preserveFilenames()->rules(['nullable','file'])
                    ->previewable(true)->downloadable(false)->openable(false)
                    ->visible(fn () => auth()->user()?->hasRole('Admin') ?? false)
                    ->disabledOn('view'),
            ])->columns(2),

            Section::make()
                ->heading(fn (Get $get, ?ImmLampiran $record) =>
                    ($record && str_contains(ltrim((string)($record->documentable_type ?? ''), '\\'), 'ImmAuditInternal'))
                        ? 'Riwayat Temuan'
                        : 'Riwayat lampiran'
                )
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