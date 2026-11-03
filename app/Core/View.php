<?php
namespace App\Core;

final class View
{
    public static array $meta = [];

    public static function partial(string $tpl, array $data = []): string
    {
        $file = APP . '/Views/' . $tpl . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $tpl");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function page(string $tpl, array $data = [], string $layout = 'layout'): void
    {
        $content = self::partial($tpl, $data);
        echo self::partial($layout, ['content' => $content] + $data);
    }

    public static function render(string $tpl, array $data = [], string $layout = 'layout'): void
    {
        self::page($tpl, $data, $layout);
    }
}
