<?php

declare(strict_types=1);

namespace Dispatch\Core;

class View
{
    /**
     * Render a view within a layout.
     *
     * @param string $view  Path relative to /views/, e.g. 'campaigns/index'
     * @param array  $data  Variables to extract into the view
     * @param string $layout Layout name from /views/layouts/, e.g. 'dashboard', 'app', 'auth'
     */
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        // Add global data available to all views
        $data['_auth'] = Auth::check();
        $data['_user'] = Auth::user();
        $data['_flash'] = Session::getAndClearFlash();
        // Only signed-in pages have forms; an anonymous visitor to the
        // marketing pages must not be given a session just to render them.
        $data['_csrf'] = $data['_auth'] ? Auth::csrfToken() : '';
        $data['_appName'] = DP_APP_NAME;
        $data['_base'] = Url::prefix();

        // Extract variables into scope
        extract($data);

        // Render the view into a buffer
        $viewPath = DP_ROOT . '/views/' . $view . '.php';
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        ob_start();
        include $viewPath;
        $content = ob_get_clean();

        // Render the layout with $content
        $layoutPath = DP_ROOT . '/views/layouts/' . $layout . '.php';
        if (file_exists($layoutPath)) {
            include $layoutPath;
        } else {
            echo $content;
        }
    }

    /**
     * Render a partial view (no layout).
     */
    public static function partial(string $partial, array $data = []): string
    {
        extract($data);
        ob_start();
        include DP_ROOT . '/views/partials/' . $partial . '.php';
        return ob_get_clean();
    }

    /**
     * Escape a value for HTML output.
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Format a date for display.
     */
    public static function date(string $date, string $format = 'M j, Y'): string
    {
        if (!$date) return '—';
        return date($format, strtotime($date));
    }

    /**
     * Return a relative time string (e.g. "in 3 days", "2 days ago").
     */
    public static function relativeDate(string $date): string
    {
        $timestamp = strtotime($date);
        $now = time();
        $diff = $timestamp - $now;
        $days = abs((int) round($diff / 86400));

        if ($days === 0) return 'Today';
        if ($diff > 0) {
            return $days === 1 ? 'Tomorrow' : "in {$days} days";
        }
        return $days === 1 ? 'Yesterday' : "{$days} days ago";
    }
}
