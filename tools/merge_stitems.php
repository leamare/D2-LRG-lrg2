<?php 

require("modules/commons/wrap_data.php");

ini_set('memory_limit', '16192M');

$input = $argv[1];
$sources = [];

for ($i=2; isset($argv[$i]); $i++) {
  $sources[] = $argv[$i];
}

$src = file_get_contents($input);
$report = json_decode($src, true);

if (empty($report)) die("No such file");

$source_reports = [];
foreach ($sources as $s) {
  $source_reports[] = json_decode(file_get_contents($s), true);
}

$report['starting_items'] = [];
$report['starting_items']['matches'] = [];

// matches stats
// l = limit
foreach ($source_reports as $s => &$rep) {
  if (!isset($rep['starting_items'])) continue;
  if (!isset($rep['starting_items']['matches'])) continue;

  foreach ($rep['starting_items']['matches'] as $role => $data) {
    $data = unwrap_data($data);

    if (empty($report['starting_items']['matches'][$role])) {
      $report['starting_items']['matches'][$role] = $data;
      continue;
    }

    foreach ($data as $hid => $stats) {
      $oldstats =& $report['starting_items']['matches'][$role][$hid];
      $oldstats['wr'] = (($oldstats['wr'] * $oldstats['m']) + ($stats['wr'] * $stats['m']))/($oldstats['m'] + $stats['m']);
      $oldstats['m'] += $stats['m'];
      $oldstats['l'] += $stats['l'];
    }
  }
}

// items stats
foreach ($source_reports as $s => &$rep) {
  if (!isset($rep['starting_items'])) continue;
  if (!isset($rep['starting_items']['items'])) continue;

  foreach ($rep['starting_items']['items'] as $role => $data) {
    if (empty($report['starting_items']['items'][$role])) {
      $report['starting_items']['items'][$role] = [];
    }

    foreach ($data as $hid => $stats) {
      $stats['head'] = $rep['starting_items']['items_head'];
      $stats = unwrap_data($stats);

      if (empty($report['starting_items']['items'][$role][$hid])) {
        $report['starting_items']['items'][$role][$hid] = $stats;
        continue;
      }

      foreach($stats as $item => $vals) {
        $oldstats =& $report['starting_items']['items'][$role][$hid][$item];

        if (empty($oldstats['matches'])) $oldstats['matches'] = 0;
        if (empty($oldstats['wins'])) $oldstats['wins'] = 0;
        if (empty($oldstats['lane_wins'])) $oldstats['lane_wins'] = 0;

        $oldstats['matches'] += $vals['matches'];
        $oldstats['wins'] += $vals['wins'];
        $oldstats['lane_wins'] += $vals['lane_wins'];
        $oldstats['freq'] = $oldstats['matches'] / $report['starting_items']['matches'][$role][$hid]['m'];
      }
    }
  }
}

// builds
foreach ($source_reports as $s => &$rep) {
  if (!isset($rep['starting_items'])) continue;
  if (!isset($rep['starting_items']['builds'])) continue;

  foreach ($rep['starting_items']['builds'] as $role => $data) {
    $data = unwrap_data($data);

    if (empty($report['starting_items']['builds'][$role])) {
      $report['starting_items']['builds'][$role] = [];
    }

    foreach ($data as $hid => $builds) {
      // $oldstats =& $report['starting_items']['matches'][$role][$hid];
      foreach ($builds as $build) {
        if (empty($build)) continue;

        $tag = implode(',', $build['build']);
        if (empty($report['starting_items']['builds'][$role][$hid][$tag])) {
          $report['starting_items']['builds'][$role][$hid][$tag] = $build;
          continue;
        }

        $oldstats =& $report['starting_items']['builds'][$role][$hid][$tag];

        if (empty($oldstats['matches'])) $oldstats['matches'] = 0;
        if (empty($oldstats['wins'])) $oldstats['wins'] = 0;
        if (empty($oldstats['lane_wins'])) $oldstats['lane_wins'] = 0;

        $oldstats['matches'] += $build['matches'];
        $oldstats['wins'] += $build['wins'];
        $oldstats['lane_wins'] += $build['lane_wins'];

        $oldstats['winrate'] = $oldstats['wins'] / $oldstats['matches'];
        $oldstats['lane_wr'] = $oldstats['lane_wins'] / $oldstats['matches'];
        $oldstats['ratio'] = $oldstats['matches'] / $report['starting_items']['matches'][$role][$hid]['m'];
      }
    }
  }
}

// consumables
foreach ($source_reports as $s => &$rep) {
  if (!isset($rep['starting_items'])) continue;
  if (!isset($rep['starting_items']['consumables'])) continue;

  foreach ($rep['starting_items']['consumables'] as $t => $data) {
    if (empty($report['starting_items']['consumables'][$t])) {
      $report['starting_items']['consumables'][$t] = [];
    }

    foreach ($data as $role => $heroes) {
      if (empty($report['starting_items']['consumables'][$t][$role])) {
        $report['starting_items']['consumables'][$t][$role] = [];
      }

      foreach ($heroes as $hid => $items) {
        $items['head'] = $rep['starting_items']['cons_head'];
        $items = unwrap_data($items);

        if (empty($report['starting_items']['consumables'][$t][$role][$hid])) {
          $report['starting_items']['consumables'][$t][$role][$hid] = $items;
          continue;
        }

        foreach ($items as $iid => $stats) {
          if (empty($report['starting_items']['consumables'][$t][$role][$hid][$iid])) {
            $report['starting_items']['consumables'][$t][$role][$hid][$iid] = $stats;
            continue;
          }

          $oldstats =& $report['starting_items']['consumables'][$t][$role][$hid][$iid];

          $m = $oldstats['matches'] ?? 0;

          $oldstats['min'] = min($oldstats['min'] ?? 0, $stats['min']);
          $oldstats['max'] = max($oldstats['max'] ?? 0, $stats['max']);
          $oldstats['total'] = ($oldstats['total'] ?? 0) + $stats['total'];
          $oldstats['matches'] = ($oldstats['matches'] ?? 0) + $stats['matches'];

          if (empty($oldstats['q1'])) $oldstats['q1'] = 0;
          if (empty($oldstats['q3'])) $oldstats['q3'] = 0;
          if (empty($oldstats['med'])) $oldstats['med'] = 0;

          $oldstats['q1'] = round( (
            ($oldstats['q1'] * $m) + 
            ($stats['q1'] * $stats['matches'])
          ) / ($m + $stats['matches']) );

          $oldstats['q3'] = round( (
            ($oldstats['q3'] * $m) + 
            ($stats['q3'] * $stats['matches'])
          ) / ($m + $stats['matches']) );

          $oldstats['med'] = round( (
            ($oldstats['med'] * $m) + 
            ($stats['med'] * $stats['matches'])
          ) / ($m + $stats['matches']) );
        }
      }
    }
  }
}

// filter builds by L
if (!empty($report['starting_items']['builds'])) {
  foreach ($report['starting_items']['builds'] as $role => $data) {
    foreach ($data as $hid => $builds) {
      foreach ($builds as $tag => $build) {
        if ($build['matches'] < $report['starting_items']['matches'][$role][$hid]['l']) {
          unset($report['starting_items']['builds'][$role][$hid][$tag]);
        }
      }
      $report['starting_items']['builds'][$role][$hid] = array_values($report['starting_items']['builds'][$role][$hid]);
    }
  }
}


// wrapping up
if (!empty($report['starting_items']['matches'])) {
  foreach ($report['starting_items']['matches'] as $role => $data) {
    $report['starting_items']['matches'][$role] = wrap_data($data, true, true, true);
  }
}
if (!empty($report['starting_items']['items'])) {
  foreach ($report['starting_items']['items'] as $role => $data) {
    foreach ($data as $hid => $stats) {
      $report['starting_items']['items'][$role][$hid] = wrap_data($stats, true, true, true);
      unset($report['starting_items']['items'][$role][$hid]['head']);
    }
  }
  $report['starting_items']['items_head'] = [ "matches", "wins", "lane_wins", "freq" ];
}
if (!empty($report['starting_items']['builds'])) {
  foreach ($report['starting_items']['builds'] as $role => $data) {
    $report['starting_items']['builds'][$role] = wrap_data($data, true, true, true);
  }
}
if (!empty($report['starting_items']['consumables'])) {
  foreach ($report['starting_items']['consumables'] as $blk => $roles) {
    foreach ($roles as $role => $heroes) {
      foreach ($heroes as $hid => $items) {
        $report['starting_items']['consumables'][$blk][$role][$hid] = wrap_data($items, true, true, true);
        unset($report['starting_items']['consumables'][$blk][$role][$hid]['head']);
      }
    }
  }
  $report['starting_items']['cons_head'] = [ "min", "q1", "med", "q3", "max", "total", "matches" ];
}


file_put_contents($input.".old", $src);
file_put_contents($input, json_encode($report));