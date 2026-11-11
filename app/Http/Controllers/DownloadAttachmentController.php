<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAttachmentController extends Controller
{
    /**
     * Unduh lampiran hanya kalau user boleh melihat data induknya.
     */
    public function __invoke(Attachment $attachment): StreamedResponse
    {
        // Induk dimuat lewat global scope (perusahaan, kategori), jadi data yang tidak boleh dilihat terbaca kosong.
        $attachable = $attachment->attachable;

        abort_if($attachable === null, 404);

        Gate::authorize('view', $attachable);

        return Storage::disk(Attachment::DISK)->download($attachment->path, $attachment->nama_file);
    }
}
