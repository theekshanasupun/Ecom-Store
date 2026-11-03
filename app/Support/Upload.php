<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class Upload
{
    private const IMG = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Save an image into public/uploads/{dir}; returns the relative path or null. */
    public static function image(?UploadedFile $f, string $dir = 'products', int $maxMb = 6): ?string
    {
        if (! $f || ! $f->isValid()) return null;
        if ($f->getSize() > $maxMb * 1048576) throw new \RuntimeException("Image is larger than {$maxMb}MB.");
        $ext = strtolower($f->getClientOriginalExtension());
        if ($ext === 'ico' && $dir === 'site') {
            // favicon: verify the ICO header (00 00 01 00)
            if (@file_get_contents($f->getRealPath(), false, null, 0, 4) !== "\x00\x00\x01\x00") throw new \RuntimeException('That file is not a valid .ico icon.');
        } else {
            if (! in_array($ext, self::IMG, true)) throw new \RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
            $info = @getimagesize($f->getRealPath());
            if (! $info) throw new \RuntimeException('That file is not a valid image.');
        }
        $path = public_path('uploads/'.$dir);
        File::ensureDirectoryExists($path);
        $name = date('ym').'-'.Str::lower(Str::random(16)).'.'.($ext === 'jpeg' ? 'jpg' : $ext);
        $f->move($path, $name);

        return $dir.'/'.$name;
    }

    public static function images(array|UploadedFile|null $files, string $dir = 'products'): array
    {
        $out = [];
        foreach ((array) $files as $f) {
            if ($f instanceof UploadedFile && ($p = self::image($f, $dir))) $out[] = $p;
        }

        return $out;
    }

    /** Private downloadable file → storage/app/private-files. */
    public static function privateFile(?UploadedFile $f): ?string
    {
        if (! $f || ! $f->isValid()) return null;
        $ext = strtolower($f->getClientOriginalExtension());
        if (in_array($ext, ['php', 'phtml', 'phar', 'php5', 'php7', 'php8', 'htaccess'], true)) throw new \RuntimeException('That file type is not allowed.');
        $name = bin2hex(random_bytes(10)).'_'.preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($f->getClientOriginalName()));
        File::ensureDirectoryExists(storage_path('app/private-files'));
        $f->move(storage_path('app/private-files'), $name);

        return $name;
    }

    public static function remove(?string $rel): void
    {
        if ($rel && ! str_contains($rel, '..') && is_file(public_path('uploads/'.$rel))) @unlink(public_path('uploads/'.$rel));
    }
}
