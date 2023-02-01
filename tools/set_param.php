<?php

require_once("modules/commons/readline.php");

if (count($argv) < 2) {
  die("Not enough args passed.\n");
}

$rep = $argv[1];

if (!file_exists("leagues/$rep.json")) {
  die("No report config for $rep.\n");
}

$config = json_decode(
  file_get_contents("leagues/$rep.json"),
  true
);

function set_param(&$config, $handle, $value) {
  $handle = explode(".", $handle);

  foreach ($handle as $level) {
    if(!isset($config[$level])) $config[$level] = [];
    $config = &$config[$level];
  }
  $config = $value;
}

function parse_param($line) {
  [$k, $v] = explode("=", trim($line), 2);
    
  if (strpos($v, "[") === 0 || strpos($v, "{") === 0) {
    $v = json_decode($v);
  } else if (strcasecmp($v, "true") === 0) {
    $v = true;
  } else if (strcasecmp($v, "false") === 0) {
    $v = false;
  } else if (is_numeric($v)) {
    $v = $v+0;
  }

  return [$k, $v];
}

$cnt = 0;

if(!isset($argv[2])) {
  echo "[ ] Enter parameters below in format \"Parameter = value\".\n    Divide parameters subcategories by a \".\", empty line to exit.\n";
  while (!empty($st = readline_rg(" >  "))) {
    [$k, $v] = parse_param($st);
    
    set_param($config, $k, $v);

    $cnt++;
  }
} else {
  [$k, $v] = parse_param($argv[2]);
    
  set_param($config, $k, $v);

  $cnt++;
}

if ($cnt) file_put_contents("leagues/$rep.json", json_encode($config, JSON_PRETTY_PRINT));