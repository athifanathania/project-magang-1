<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ImmLampiranResource\Pages;
use App\Models\ImmLampiran;
use Filament\Forms;
use Filament\Forms\Components\{Hidden, Group};
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Forms\Components\{Select, TextInput, TagsInput, FileUpload, Section, View as ViewField};

class ImmLampiranResource extends Resource
{
    protected static ?string $model = ImmLampiran::class;
    public static function getNavigationLabel(): string { return 'IMM Lampiran'; }
    public static function shouldRegisterNavigation(): bool { return false; } // sembunyikan

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([

                // ⚠️ HAPUS / SEMBUNYIKAN field relasi saat view (morphTo bikin error)
                // Kalau perlu pada create/edit saja, aktifkan kembali dan kasih ->hiddenOn('view')
                // Forms\Components\Select::make('parent_id')
                //     ->label('Parent (opsional)')
                //     ->relationship('parent', 'nama')
                //     ->searchable()->preload()
                //     ->hiddenOn('view'),
                //
                // (Untuk 'documentable' gunakan MorphToSelect jika memang mau dipakai; di view kita sembunyikan)

                TextInput::make('nama')
                    ->label('Nama Lampiran')
                    ->required()
                    ->disabledOn('view'),

                TagsInput::make('keywords')
                    ->label('Kata Kunci')
                    ->separator(',')
                    ->reorderable()
                    ->disabledOn('view'),

                FileUpload::make('file')
                    ->label('File')
                    ->disk('private')
                    ->directory('imm/lampiran')
                    ->preserveFilenames()
                    ->downloadable(false)
                    ->openable(true)
                    ->disabledOn('view')   // ⬅️ kunci di modal viewer
                    ->saveUploadedFileUsing(function ($file, $record) {
                        if ($record) {
                            $ver = $record->addVersionFromUpload($file);
                            return $ver['file'] ?? $record->file;
                        }
                        return $file->store('imm/tmp', 'private');
                    }),
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
