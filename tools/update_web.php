<?php

/**
 * Re-analyze a league and write the report into the configured web reports tree.
 *
 * Usage (from repo root):
 *   php tools/update_web.php -lLEAGUE_TAG [-r/path/to/web] [-Ttemplate]
 *
 * Uses web_reports_dir from rg_settings.json (setup.php "Web reports location"),
 * or -r. Locates the existing report via res/cachelist.json / reports/report_<tag>.json,
 * then runs rg_analyzer with that output path.
 */

$root = dirname(__DIR__);
chdir($root);

require_once $root . '/head.php';

if (!isset($lrg_league_tag)) {
    die("[F] Pass -lLEAGUE_TAG.\n");
}

// Re-include l: so glued -lTAG stays one option. -r = web root (head already uses -w).
$tool_opts = getopt('l:r:T:');
$webRoot = $tool_opts['r'] ?? ($lrg_web_reports_dir ?? '');
if (is_array($webRoot)) {
    $webRoot = end($webRoot);
}
$webRoot = rtrim(trim((string)$webRoot), "/\\");
if ($webRoot === '') {
    die("[F] No web reports location. Set web_reports_dir via setup.php or pass -r/path/to/web.\n");
}
if (!is_dir($webRoot)) {
    die("[F] Web reports path is not a directory: {$webRoot}\n");
}

$reportsDir = $webRoot . '/reports';
$cacheFile = $webRoot . '/res/cachelist.json';
$prefix = 'report_';
$suffix = '.json';

$reportPath = null;

// Same first-try path the web frontends use.
$direct = $reportsDir . '/' . $prefix . $lrg_league_tag . $suffix;
if (is_readable($direct)) {
    $reportPath = $direct;
}

if ($reportPath === null && is_readable($cacheFile)) {
    $cache = json_decode((string)file_get_contents($cacheFile), true);
    $rel = $cache['reps'][$lrg_league_tag]['file'] ?? null;
    if (is_string($rel) && $rel !== '') {
        foreach ([$reportsDir . '/' . $rel, $webRoot . '/' . $rel] as $cand) {
            if (is_readable($cand)) {
                $reportPath = $cand;
                break;
            }
        }
    }
}

if ($reportPath === null && is_dir($reportsDir)) {
    $needle = $prefix . $lrg_league_tag . $suffix;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($reportsDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile() && $file->getFilename() === $needle) {
            $reportPath = $file->getPathname();
            break;
        }
    }
}

if ($reportPath === null) {
    die("[F] No web report found for `{$lrg_league_tag}` under {$webRoot}\n");
}

echo "[ ] Found web report: {$reportPath}\n";

$php = escapeshellarg(PHP_BINARY);
$analyzer = escapeshellarg($root . '/rg_analyzer.php');
$cmd = "{$php} {$analyzer} -l" . escapeshellarg($lrg_league_tag) . ' -o' . escapeshellarg($reportPath);
if (!empty($tool_opts['T'])) {
    $tpl = is_array($tool_opts['T']) ? end($tool_opts['T']) : $tool_opts['T'];
    $cmd .= ' -T' . escapeshellarg((string)$tpl);
}

echo "[ ] Running: {$cmd}\n";
passthru($cmd, $code);
if ($code !== 0) {
    die("[F] rg_analyzer exited with code {$code}\n");
}
echo "[S] Updated web report for `{$lrg_league_tag}` → {$reportPath}\n";
