<?php

declare(strict_types=1);

namespace TimeBank\Core;

class View
{
    /** Old input for this render, read by old(). */
    public static array $old = [];

    /**
     * Render a view wrapped in a layout.
     *
     * @param string $template  under src/Views/, e.g. 'offers/index'
     * @param string $layout    under src/Views/, e.g. 'layout/base'
     */
    public static function render(string $template, array $data = [], string $layout = 'layout/base'): void
    {
        $viewPath   = TM_ROOT . '/src/Views/' . $template . '.php';
        $layoutPath = TM_ROOT . '/src/Views/' . $layout . '.php';

        if (!is_file($viewPath)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
            exit;
        }

        extract(self::shared(), EXTR_SKIP);
        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if (is_file($layoutPath)) {
            require $layoutPath;
        } else {
            echo $content;
        }
    }

    public static function renderPartial(string $template, array $data = []): void
    {
        $viewPath = TM_ROOT . '/src/Views/' . $template . '.php';
        if (!is_file($viewPath)) {
            echo 'Partial not found: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
            return;
        }
        extract(self::shared(), EXTR_SKIP);
        extract($data, EXTR_SKIP);
        require $viewPath;
    }

    /** Variables every view gets. Reading them never creates a session. */
    private static function shared(): array
    {
        $errors = $old = [];
        if (tl_session()) {
            $errors = $_SESSION['tm_errors'] ?? [];
            $old    = $_SESSION['tm_old'] ?? [];
            unset($_SESSION['tm_errors'], $_SESSION['tm_old']);
        }

        self::$old = $old;

        return [
            'auth'   => new Auth(),
            'tenant' => Tenant::get(),
            'flash'  => Flash::all(),
            'errors' => $errors,
            'old'    => $old,
        ];
    }
}
