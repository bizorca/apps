<?php
/**
 * One cheap request through the Batch API, to confirm the envelope before
 * spending a book on it. Flash-lite at 1K in batch is about $0.017.
 *
 *   php batch_probe.php submit
 *   php batch_probe.php check batches/xyz
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
define('AF_ROOT', $root);
require $root . '/includes/boot.php';
use Anglerfish\Services\GeminiBatch;

$cmd = $argv[1] ?? "submit";
if ($cmd === "submit") {
    $req = [GeminiBatch::imageRequest(
        "A single navy anchor drawn in flat editorial style on a pale cream ground, "
        . "with the word ANCHOR beneath it in small capitals.",
        "probe1", "16:9", "1K")];
    $res = GeminiBatch::submit("gemini-3.1-flash-lite-image", $req, "anglerfish-probe");
    echo json_encode($res), "\n";
    exit(0);
}
$op = GeminiBatch::status($argv[2]);
echo "state: ", GeminiBatch::stateOf($op), "  done: ", var_export(GeminiBatch::isDone($op), true), "\n";
$h = GeminiBatch::harvest($op);
echo "images: ", count($h["images"]), "  keys: ", implode(",", array_keys($h["images"])), "\n";
echo "errors: ", json_encode($h["errors"]), "\n";
echo substr(json_encode(GeminiBatch::sanitise($op), JSON_PRETTY_PRINT), 0, 2500), "\n";
