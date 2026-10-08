<?php

namespace Anglerfish\Core;

final class View
{
    public static function render(string $template, array $data = [], string $title = ''): void
    {
        $dir = dirname(__DIR__, 2) . '/templates';
        $file = "$dir/$template.php";
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: $template");
        }

        extract($data, EXTR_SKIP);
        $pageTitle = $title;

        ob_start();
        require $file;
        $content = ob_get_clean();

        require "$dir/layout.php";
    }
}
