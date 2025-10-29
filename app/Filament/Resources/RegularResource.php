<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegularResource\Pages;
use App\Filament\Support\FileCell;
use App\Filament\Support\RowClickViewForNonEditors;
use App\Models\Regular;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegularResource extends Resource
{
    protected static ?string $model = Regular::class;

    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Regular';
    protected static ?string $navigationGroup = 'Dokumen Eksternal';
    protected static ?int    $navigationSort  = 0;

    protected static ?string $modelLabel       = 'dokumen';
    protected static ?string $pluralModelLabel = 'Regular';

    use RowClickViewForNonEditors, FileCell;

    public static function form(Form $form): Form
    {
        // Ambil form base dari BerkasResource
        $baseForm = \App\Filament\Resources\BerkasResource::form($form);

        // Ambil komponen (v3: getComponents), lalu sesuaikan direktori/file hint untuk Regular
        $components = collect($baseForm->getComponents())->map(function ($c) {
            if ($c instanceof \Filament\Forms\Components\FileUpload) {
                // Dokumen utama → simpan di folder 'regular'
                if ($c->getName() === 'dokumen') {
                    $c->disk('private')->directory('regular')
                        ->hintAction(
                            \Filament\Forms\Components\Actions\Action::make('openFile')
                                ->label('Buka file')
                                ->url(
                                    fn ($record) => ($record && $record->dokumen)
                                        ? route('media.regular', $record)
                                        : null,
                                    shouldOpenInNewTab: true
                                )
                                ->visible(fn ($record) =>
                                    filled($record?->dokumen)
                                    && (auth()->user()?->hasAnyRole(['Admin','Editor','Staff']) ?? false)
                                )
                        );
                }

                // Thumbnail → tetap di public, tapi pisahkan folder biar rapi
                if ($c->getName() === 'thumbnail') {
                    $c->disk('public')->directory('regular/thumbnails');
                }

                // File sumber (admin) → pisahkan juga
                if ($c->getName() === 'dokumen_src') {
                    $c->disk('private')->directory('regular/_source');
                }
            }

            // Detail event untuk halaman Regular harus fix "Regular"
            if ($c instanceof \Filament\Forms\Components\TextInput && $c->getName() === 'detail') {
                $c->default('Regular')->disabled(); // biar selalu "Regular"
            }

            return $c;
        })->all();

        // Pasang kembali ke form Regular
        return $form->schema($components);
    }

    public static function table(Table $table): Table
    {
        // ambil tabel dari BerkasResource biar identik
        return \App\Filament\Resources\BerkasResource::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRegular::route('/'),
            'create' => Pages\CreateRegular::route('/create'),
            'edit'   => Pages\EditRegular::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['lampirans']);
        return $query->orderByRaw('LOWER(cust_name) ASC')
                     ->orderByRaw('LOWER(model) ASC');
    }

    public static function canViewAny(): bool
    {
        // samakan izin dengan Berkas/Event
        return \App\Filament\Resources\BerkasResource::canViewAny();
    }
    public static function canCreate(): bool
    {
        return \App\Filament\Resources\BerkasResource::canCreate();
    }
    public static function canDelete($record): bool
    {
        return \App\Filament\Resources\BerkasResource::canDelete($record);
    }
    public static function canDeleteAny(): bool
    {
        return \App\Filament\Resources\BerkasResource::canDeleteAny();
    }
    public static function shouldRegisterNavigation(): bool
    {
        return \App\Filament\Resources\BerkasResource::shouldRegisterNavigation();
    }
}
