<?php
declare(strict_types=1);

/**
 * Report PDFs. Rendered with the vendored dompdf on first download, cached at
 * NB_DATA/<user_id>/report-<id>.pdf (private_html/data/numbrella, never under
 * the web root) and served only by public/report-pdf.php after its owner check.
 * finalizeReport() deletes the cached file whenever a report is re-run.
 */

function reportPdfPath(int $userId, int $reportId): string
{
    return NB_DATA . '/' . $userId . '/report-' . $reportId . '.pdf';
}

function deleteReportPdf(int $userId, int $reportId): void
{
    $file = reportPdfPath($userId, $reportId);
    if (is_file($file)) {
        unlink($file);
    }
}

/** Path to the report's PDF, rendering and caching it first if needed. */
function ensureReportPdf(array $report): string
{
    $file = reportPdfPath((int) $report['user_id'], (int) $report['id']);
    if (is_file($file)) {
        return $file;
    }

    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create ' . $dir);
    }

    $pdf = renderReportPdf($report);

    // Write then rename, so a concurrent download never reads half a file.
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, $pdf);
    rename($tmp, $file);

    return $file;
}

/** Render a completed report as a PDF and return the raw PDF string. */
function renderReportPdf(array $report): string
{
    require_once NB_ROOT . '/includes/vendor/autoload.php';

    // dompdf writes font metric caches as it goes. Keep them in the writable
    // data folder: the vendored lib/fonts is owned by the deploy user.
    $cache = NB_DATA . '/dompdf';
    if (!is_dir($cache)) {
        mkdir($cache, 0775, true);
    }

    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('isJavascriptEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('fontCache', $cache);
    $options->set('tempDir', $cache);
    $options->set('chroot', [NB_ROOT . '/templates', NB_ROOT . '/includes/vendor/dompdf/lib']);

    $dompdf = new \Dompdf\Dompdf($options);

    ob_start();
    try {
        include NB_ROOT . '/templates/pdf/report.php';
    } finally {
        $html = (string) ob_get_clean();
    }

    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    return (string) $dompdf->output();
}
