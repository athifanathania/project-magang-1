<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class EventCustomer extends Model
{
    use HasFactory;

    protected $table = 'event_customers';

    protected $fillable = [
        'thumbnail',
        'cust_name',
        'model',
        'kode_berkas',
        'nama',
        'detail',
        'keywords',
        'dokumen',
        'dokumen_src',
        'is_public',
    ];

    protected $casts = [
        'keywords' => 'array', // Penting untuk TagsInput
        'is_public' => 'boolean',
    ];

    /**
     * Relasi ke Lampiran (Asumsi Polymorphic karena Resource pakai whereHas 'lampirans')
     */
    public function lampirans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Lampiran::class, 'berkas_id');
    }

    /**
     * Untuk fitur modal lampiran recursive
     */
    public function rootLampirans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->lampirans()->whereNull('parent_id');
    }

    /**
     * Logika Versioning Dokumen (Dipanggil di Resource)
     */
    public function addVersionFromUpload($file)
    {
        // 1. Simpan file baru
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('event_customers', $filename, 'private');

        // 2. Logic Versioning (Contoh Sederhana)
        // Jika Anda punya tabel 'file_versions', simpan history di sini.
        // \App\Models\FileVersion::create([
        //     'event_customer_id' => $this->id,
        //     'path' => $this->dokumen, // path lama
        //     'created_at' => now(),
        // ]);

        // 3. Update field dokumen di record ini
        $this->update(['dokumen' => $path]);

        return ['file_path' => $path];
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }
}