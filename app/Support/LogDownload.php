<?php
namespace App\Support;

use Spatie\Activitylog\Models\Activity;

class LogDownload
{
    public static function make(array $data): void
    {
        activity('web')
            ->causedBy(auth()->user())
            ->withProperties([
                'page'       => $data['page']       ?? null,
                'type'       => $data['type']       ?? null,
                'file'       => $data['file']       ?? null,
                'version'    => $data['version']    ?? null,
                'record_id'  => $data['record_id']  ?? null,
                'path'       => $data['path']       ?? null,
            ])
            ->log('download');
    }
}
