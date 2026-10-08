<?php

declare(strict_types=1);

/**
 * Minimal test harness. No PHPUnit — Pilotage has no Composer dependencies,
 * and Phase 0's suite is small enough that a hundred lines of runner is
 * cheaper than a toolchain.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// The shared tools core: Session, sign-in and invitations call tl_user(),
// tl_session() and tl_register(). Under private_html/ in the repo; this suite
// is never deployed, so only the repo layout matters.
require_once dirname(__DIR__, 2) . '/private_html/includes/bootstrap.php';

require dirname(__DIR__) . '/src/Core/autoload.php';

use Bizorca\Pilotage\Core\Config;

final class T
{
    public static int $passed = 0;
    /** @var string[] */
    public static array $failures = [];
    private static string $group = '';

    public static function group(string $name): void
    {
        self::$group = $name;
        echo "\n  " . $name . "\n";
    }

    public static function ok(bool $condition, string $description): void
    {
        if ($condition) {
            self::$passed++;
            echo "    pass  " . $description . "\n";
            return;
        }
        self::$failures[] = self::$group . ' :: ' . $description;
        echo "    FAIL  " . $description . "\n";
    }

    public static function same(mixed $expected, mixed $actual, string $description): void
    {
        if ($expected === $actual) {
            self::$passed++;
            echo "    pass  " . $description . "\n";
            return;
        }
        $detail = sprintf(
            '%s (expected %s, got %s)',
            $description,
            var_export($expected, true),
            var_export($actual, true)
        );
        self::$failures[] = self::$group . ' :: ' . $detail;
        echo "    FAIL  " . $detail . "\n";
    }

    /** Assert that $fn throws $exceptionClass. */
    public static function throws(string $exceptionClass, callable $fn, string $description): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            if ($e instanceof $exceptionClass) {
                self::$passed++;
                echo "    pass  " . $description . "\n";
                return;
            }
            self::$failures[] = self::$group . ' :: ' . $description . ' (threw ' . get_class($e) . ')';
            echo "    FAIL  " . $description . " (threw " . get_class($e) . ")\n";
            return;
        }

        self::$failures[] = self::$group . ' :: ' . $description . ' (did not throw)';
        echo "    FAIL  " . $description . " (did not throw)\n";
    }

    public static function summary(): int
    {
        $failed = count(self::$failures);

        echo "\n" . str_repeat('-', 60) . "\n";
        echo self::$passed . " passed, " . $failed . " failed\n";

        if ($failed > 0) {
            echo "\nFailures:\n";
            foreach (self::$failures as $f) {
                echo "  - " . $f . "\n";
            }
            echo "\n";
            return 1;
        }

        echo "\n";
        return 0;
    }
}

/**
 * Load config pointed at the TEST schema. Tests truncate tables, so pointing
 * this at the dev database would be rude.
 */
function test_config(): array
{
    $config = require dirname(__DIR__) . '/config/app.php';

    // A scratch schema of its own, never the shared tools database: the
    // suite creates and deletes firms freely. tests/schema.php builds it.
    $testDb = getenv('TEST_DB_NAME') ?: (string) tl_env('PL_TEST_DB_NAME', 'pilotage_tools_test');

    $config['db']['shared'] = false;
    $config['db']['name'] = (string) $testDb;
    $config['app']['env'] = 'testing';
    // Uploads and the mail log go to a temp dir, never the real
    // private_html/data/pilotage that a dev server is reading.
    $config['app']['storage'] = sys_get_temp_dir() . '/pilotage-tools-tests';

    return $config;
}
