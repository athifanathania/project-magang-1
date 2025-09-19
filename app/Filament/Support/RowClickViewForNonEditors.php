<?php

namespace App\Filament\Support;

use Filament\Tables\Table;

trait RowClickViewForNonEditors
{
    protected static function isEditorOrAdmin(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Editor']) ?? false;
    }

    /** Terapkan: Staff/Viewer klik row => 'view', Admin/Editor => edit URL */
    protected static function applyRowClickPolicy(Table $table): Table
    {
        return $table
            ->recordAction(fn () => static::isEditorOrAdmin() ? null : 'view')
            ->recordUrl(fn ($record) => static::isEditorOrAdmin()
                ? static::getUrl('edit', ['record' => $record])
                : null
            );
    }
}
