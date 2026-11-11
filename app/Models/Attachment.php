<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['nama_file', 'path', 'ukuran', 'diunggah_oleh'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, LogsChanges;

    // Lampiran disimpan di disk privat dan hanya diunduh lewat route yang memeriksa izin.
    public const string DISK = 'local';

    protected static function booted(): void
    {
        static::deleted(function (Attachment $attachment): void {
            Storage::disk(self::DISK)->delete($attachment->path);
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }
}
