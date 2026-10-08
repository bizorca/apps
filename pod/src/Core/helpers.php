<?php

function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    $path = PD_ROOT . '/src/Views/' . $view . '.php';
    if (!file_exists($path)) {
        http_response_code(500);
        echo 'View not found: ' . h($view);
        return;
    }

    ob_start();
    require $path;
    $content = ob_get_clean();

    require PD_ROOT . '/src/Views/layout.php';
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
 * Every Pod URL is built here, so the query-string routing this server needs
 * today becomes clean URLs with one constant (PD_CLEAN_URLS in config.php).
 *
 *   query mode   url('forum/post/5')  ->  /pod/?r=/forum/post/5
 *   clean mode   url('forum/post/5')  ->  /pod/forum/post/5
 *
 * A path's own "?a=b" rides inside r and current_path() folds it back into
 * $_GET, so url('forum/new?category=wins') works in either mode. Never write a
 * relative href="?x=1": it drops r. A GET form needs route_field() too,
 * because a browser replaces the action's query string with the form fields.
 */
function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    if ($path === '/') {
        return PD_BASE . '/';
    }
    return (PD_CLEAN_URLS ? PD_BASE : PD_BASE . '/?r=') . $path;
}

/** Absolute, for email. */
function absolute_url(string $path): string
{
    return PD_ORIGIN . url($path);
}

/** Hidden r field for a GET form whose action is url($path). */
function route_field(string $path): string
{
    return PD_CLEAN_URLS ? '' : '<input type="hidden" name="r" value="' . h('/' . ltrim($path, '/')) . '">';
}

/** The app path of this request ('/forum/post/5'), without any query. */
function current_path(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }

    if (PD_CLEAN_URLS) {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (str_starts_with($uri, PD_BASE)) {
            $uri = substr($uri, strlen(PD_BASE));
        }
    } else {
        $uri = is_string($_GET['r'] ?? null) ? $_GET['r'] : '/';
        $q = strpos($uri, '?');
        if ($q !== false) {
            // "?r=/forum/new?category=wins": PHP put category inside r.
            // Recover it; a real top-level key wins.
            parse_str(substr($uri, $q + 1), $inner);
            $_GET += $inner;
            $uri = substr($uri, 0, $q);
        }
    }

    return $path = '/' . trim($uri, '/');
}

function csrf_field(): string
{
    return tl_csrf_field();
}

function csrf_verify(): void
{
    if (!tl_csrf_ok($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        render('error', ['code' => 403, 'message' => 'Invalid CSRF token.']);
        exit;
    }
}

function timeAgo(\DateTimeInterface|string $date): string
{
    if (is_string($date)) {
        $date = new \DateTime($date);
    }
    $diff = (new \DateTime())->getTimestamp() - $date->getTimestamp();

    return match (true) {
        $diff < 60      => 'just now',
        $diff < 3600    => floor($diff / 60) . 'm ago',
        $diff < 86400   => floor($diff / 3600) . 'h ago',
        $diff < 604800  => floor($diff / 86400) . 'd ago',
        default         => $date->format('M j, Y'),
    };
}

/** An http(s) URL, or ''. Keeps javascript: and data: out of href and src. */
function safe_url(?string $url): string
{
    $url = trim((string) $url);
    return preg_match('~^https?://~i', $url) ? $url : '';
}

function parseVideoEmbed(string $url): string
{
    // YouTube
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/', $url, $m)) {
        $id = $m[1];
        return '<iframe class="w-full aspect-video rounded-lg" src="https://www.youtube.com/embed/' . h($id) . '" frameborder="0" allowfullscreen></iframe>';
    }

    // Vimeo
    if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
        $id = $m[1];
        return '<iframe class="w-full aspect-video rounded-lg" src="https://player.vimeo.com/video/' . h($id) . '" frameborder="0" allowfullscreen></iframe>';
    }

    // Fallback: render as a plain link. Only http(s): this HTML is stored and
    // echoed raw on the lesson page, and the original accepted javascript: too.
    if (safe_url($url) !== '') {
        return '<a href="' . h($url) . '" target="_blank" rel="noopener" class="text-indigo-600 hover:underline text-sm">' . h($url) . '</a>';
    }

    return '';
}
