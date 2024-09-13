<?php 

$failed_reports = [];

$files = scandir("tmp");

foreach ($files as $f) {
  if (!preg_match("/failed_(.*)_(\d+)/", $f)) {
    continue;
  }
  
  $rep = preg_replace("/failed_(.*)_(\d+)/", "\\1", $f);

  $list = explode("\n", trim(file_get_contents("tmp/$f")));
  if (!isset($failed_reports[$rep])) {
    $failed_reports[$rep] = [];
  }

  $failed_reports[$rep] = array_merge($failed_reports[$rep], $list);

  echo "- removing `tmp/$f`\n";
  unlink("tmp/$f");
}

foreach ($failed_reports as $rep => $list) {
  $list = array_unique($list);
  echo "\t$rep\n";

  passthru("echo \"".implode("\n", $list)."\" | php rg_fetcher.php -l$rep -L");
}