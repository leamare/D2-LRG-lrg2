#!/bin/php
<?php
/**
 * matchlist_intersect.php  A.list  B.list  [-f output.list]  [-R]
 *
 * Default  : writes A ∩ B to -f (or back to A if -f is omitted).
 * With -R  : writes matches that are in A but NOT in B (set difference A \ B).
 *
 * Comment lines (starting with #) and blank lines are ignored when reading;
 * the output contains only match IDs, one per line, preserving A's order.
 */

// Manual parse so options are accepted anywhere relative to the file paths.
$file_a    = null;
$file_b    = null;
$output    = null;
$exclusive = false;

for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '-f' && isset($argv[$i + 1])) {
        $output = $argv[++$i];
    } elseif ($argv[$i] === '-R') {
        $exclusive = true;
    } elseif ($argv[$i][0] !== '-') {
        if ($file_a === null)      $file_a = $argv[$i];
        elseif ($file_b === null)  $file_b = $argv[$i];
    }
}

if ($file_a === null || $file_b === null) {
    fwrite(STDERR, "Usage: ".basename($argv[0])." <A.list> <B.list> [-f output.list] [-R]\n");
    fwrite(STDERR, "  -f <file>  Write result to this file (default: overwrite A)\n");
    fwrite(STDERR, "  -R         Output matches exclusive to A (in A but not in B)\n");
    exit(1);
}

$output = $output ?? $file_a;

function read_matchlist(string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) {
        fwrite(STDERR, "[F] Cannot read: $path\n");
        exit(1);
    }
    $ids = [];
    foreach (explode("\n", str_replace("\r\n", "\n", $raw)) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $ids[] = $line;
    }
    return array_unique($ids);
}

$a     = read_matchlist($file_a);
$b     = read_matchlist($file_b);
$b_set = array_flip($b);

if ($exclusive) {
    $result = array_values(array_filter($a, fn($id) => !isset($b_set[$id])));
    $label  = "A-exclusive (A \\ B)";
} else {
    $result = array_values(array_filter($a, fn($id) => isset($b_set[$id])));
    $label  = "intersection (A ∩ B)";
}

echo "[ ] A : ".count($a)." matches  ($file_a)\n";
echo "[ ] B : ".count($b)." matches  ($file_b)\n";
echo "[ ] $label : ".count($result)." matches\n";

$content = count($result) ? implode("\n", $result)."\n" : "";
if (file_put_contents($output, $content) === false) {
    fwrite(STDERR, "[F] Cannot write: $output\n");
    exit(1);
}

echo "[S] Written to $output\n";
