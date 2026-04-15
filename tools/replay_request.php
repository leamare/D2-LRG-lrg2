#!/bin/php
<?php
require_once("head.php");

$pass = [];
foreach (['f', 'm', 'j', 'd'] as $k) {
  if (!isset($options[$k])) {
    continue;
  }
  if (is_array($options[$k])) {
    foreach ($options[$k] as $v) {
      $pass[] = "-$k".escapeshellarg((string)$v);
    }
  } else {
    $pass[] = "-$k".escapeshellarg((string)$options[$k]);
  }
}

if (!isset($options['f']) && !isset($options['m'])) {
  die("[F] Pass -f<matchlist> or -m<matchid>\n");
}

$args = implode(' ', $pass);
$scripts = [
  "tools/replay_request_od.php",
  // "tools/replay_request_stratz.php"
];

foreach ($scripts as $script) {
  $cmd = "php ".escapeshellarg($script)." $args";
  passthru($cmd, $code);
  if ($code !== 0) {
    exit($code);
  }
}

?>
