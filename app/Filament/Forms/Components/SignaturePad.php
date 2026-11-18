<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

// Tanda tangan di kanvas; state-nya data URL PNG dari browser.
class SignaturePad extends Field
{
    protected string $view = 'filament.forms.components.signature-pad';

    private const string PREFIX = 'data:image/png;base64,';

    private const int MAX_LENGTH = 512 * 1024;

    private const int MAX_WIDTH = 2400;

    private const int MAX_HEIGHT = 1200;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helperText(__('Tanda tangani di dalam kotak dengan jari atau mouse.'));

        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (static::png($value) === null) {
                $fail(__('Tanda tangan tidak terbaca. Hapus, lalu tanda tangani lagi.'));
            }
        });
    }

    /**
     * PNG dari data URL kanvas, digambar ulang di atas latar putih supaya yang tersimpan hanya gambar. Null kalau bukan PNG yang wajar.
     */
    public static function png(mixed $dataUrl): ?string
    {
        if (! is_string($dataUrl) || ! str_starts_with($dataUrl, self::PREFIX) || strlen($dataUrl) > self::MAX_LENGTH) {
            return null;
        }

        $bytes = base64_decode(substr($dataUrl, strlen(self::PREFIX)), true);
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);

        if ($size === false || $size[2] !== IMAGETYPE_PNG || $size[0] > self::MAX_WIDTH || $size[1] > self::MAX_HEIGHT) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return null;
        }

        $image = imagecreatetruecolor($size[0], $size[1]);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopy($image, $source, 0, 0, 0, 0, $size[0], $size[1]);

        ob_start();
        imagepng($image);

        return ob_get_clean();
    }
}
