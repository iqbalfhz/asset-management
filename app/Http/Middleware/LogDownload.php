<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

// Unduhan dokumen yang berhasil dicatat di log "unduhan", supaya setelah insiden terlihat siapa mengambil data apa.
class LogDownload
{
    public const LOG = 'unduhan';

    /**
     * @param  class-string<Model>|null  $subjectClass
     */
    public static function of(string $document, ?string $subjectClass = null): string
    {
        return self::class.':'.implode(',', array_filter([$document, $subjectClass]));
    }

    /**
     * @param  class-string<Model>|null  $subjectClass  model yang id-nya ada di parameter route pertama
     */
    public function handle(Request $request, Closure $next, string $document, ?string $subjectClass = null): Response
    {
        $response = $next($request);

        if (! $response->isSuccessful()) {
            return $response;
        }

        $subjectId = collect($request->route()->parameters())
            ->map(fn (mixed $value): mixed => $value instanceof Model ? $value->getKey() : $value)
            ->first();

        activity(self::LOG)
            ->event('diunduh')
            ->withProperties(['dokumen' => $document] + $request->query())
            ->tap(function (Activity $activity) use ($subjectClass, $subjectId): void {
                if ($subjectClass !== null) {
                    $activity->subject_type = (new $subjectClass)->getMorphClass();
                    $activity->subject_id = $subjectId;
                }
            })
            ->log($document);

        return $response;
    }
}
