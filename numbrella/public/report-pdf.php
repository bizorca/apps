<?php
declare(strict_types=1);

/**
 * The report as a PDF, for its owner (or a site admin) only. Anyone else,
 * including share-link visitors, gets a 404. The file is rendered once and
 * cached under private_html/data/numbrella/<user_id>/, outside the web root.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once NB_ROOT . '/includes/pdf.php';

requireLogin();

$report = findOwnReport((int) ($_GET['id'] ?? 0));
if (!$report || $report['status'] !== 'complete') {
    notFound();
}

try {
    $file = ensureReportPdf($report);
} catch (Throwable $e) {
    error_log('numbrella pdf ' . $report['id'] . ': ' . $e->getMessage());
    http_response_code(500);
    exit('The PDF could not be generated. Please try again in a minute.');
}

$slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $report['business_name'])), '-');
$filename = 'numbrella-valuation' . ($slug !== '' ? '-' . substr($slug, 0, 40) : '') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
