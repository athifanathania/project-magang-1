<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

trait FileCell
{
    protected static function canOpenFile(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin','Editor','Staff']) ?? false;
    }

    /**
     * @param  string   $field       nama kolom file di DB (mis. 'dokumen' atau 'file')
     * @param  \Closure $urlResolver fn (Model $record): string
     */
    protected static function fileTextColumn(string $field, \Closure $urlResolver): TextColumn
    {
        return TextColumn::make($field)
            ->state(fn ($record) => data_get($record, $field) ? '📂' : '-')
            ->url(
                fn ($record) =>
                    (data_get($record, $field) && static::canOpenFile())
                        ? $urlResolver($record)
                        : null,
                shouldOpenInNewTab: true
            )
            ->tooltip(fn ($record) => data_get($record, $field)
                ? (static::canOpenFile() ? 'Buka file' : 'Tidak punya akses membuka file')
                : null
            )
            ->color(fn ($record) =>
                (data_get($record, $field) && static::canOpenFile()) ? 'primary' : null
            )
            ->extraAttributes(fn ($record) => [
                'class' => data_get($record, $field)
                    ? (static::canOpenFile()
                        ? 'text-blue-600 hover:underline'
                        : 'text-gray-300 cursor-not-allowed')
                    : 'text-gray-400',
            ]);
    }
}
