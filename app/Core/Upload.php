<?php
namespace App\Core;

final class Upload
{
    public const IMG = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];

    /** Save an uploaded image; returns relative path inside /uploads or null. */
    public static function image(?array $f, string $dir = 'products', int $maxMb = 6): ?string
    {
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            return null;
        }
        if ($f['size'] > $maxMb * 1048576) {
            throw new \RuntimeException("Image is larger than {$maxMb}MB.");
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!isset(self::IMG[$ext])) {
            throw new \RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
        }
        $info = @getimagesize($f['tmp_name']);
        if (!$info || !in_array($info['mime'], self::IMG, true)) {
            throw new \RuntimeException('That file is not a valid image.');
        }
        $dirPath = UPLOADS . '/' . $dir;
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, 0755, true);
        }
        $name = date('ym') . '-' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        if (!move_uploaded_file($f['tmp_name'], $dirPath . '/' . $name)) {
            throw new \RuntimeException('Could not save the uploaded file. Check folder permissions on /uploads.');
        }
        return $dir . '/' . $name;
    }

    /** Several images from a multi-file input. */
    public static function images(?array $f, string $dir = 'products'): array
    {
        $out = [];
        if (!$f || !is_array($f['name'] ?? null)) {
            return $out;
        }
        foreach ($f['name'] as $i => $n) {
            $one = ['name' => $n, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            $p = self::image($one, $dir);
            if ($p) $out[] = $p;
        }
        return $out;
    }

    /** Private downloadable file (stored in /storage/files). */
    public static function privateFile(?array $f): ?string
    {
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            return null;
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['php', 'phtml', 'phar', 'php5', 'php7', 'php8', 'htaccess'], true)) {
            throw new \RuntimeException('That file type is not allowed.');
        }
        $name = bin2hex(random_bytes(10)) . '_' . preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($f['name']));
        if (!move_uploaded_file($f['tmp_name'], STORAGE . '/files/' . $name)) {
            throw new \RuntimeException('Could not store the file. Check /storage/files permissions.');
        }
        return $name;
    }

    public static function remove(?string $rel): void
    {
        if ($rel && !str_contains($rel, '..')) {
            $p = UPLOADS . '/' . $rel;
            if (is_file($p)) @unlink($p);
        }
    }
}
