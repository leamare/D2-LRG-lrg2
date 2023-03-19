<?php 

require("modules/commons/wrap_data.php");

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

$report['items'] = [];
$report['items']['stats'] = [];

//stats
foreach ($source_reports as &$rep) {
  $rep['items']['stats'] = unwrap_data($rep['items']['stats']);

  if (empty($report['items']['stats'])) {
    $report['items']['stats'] = $rep['items']['stats'];
    continue;
  }

  foreach ($rep['items']['stats'] as $hid => $items) {
    $total_items_matches = $report['items']['stats'][$hid][29]['matchcount'];
    $items_matches = $rep['items']['stats'][$hid][29]['matchcount'];

    foreach ($items as $iid => $st) {
      if (empty($st)) continue;
      if (empty($report['items']['stats'][$hid][$iid])) {
        $report['items']['stats'][$hid][$iid] = $st;
        continue;
      }

      $p = $report['items']['stats'][$hid][$iid]['purchases'];

      $report['items']['stats'][$hid][$iid]['purchases'] += $st['purchases'];
      $report['items']['stats'][$hid][$iid]['matchcount'] += $st['matchcount'];
      // $report['items']['stats'][$hid][$iid]['winrate'] = (
      //   ($report['items']['stats'][$hid][$iid]['winrate'] * $p) + 
      //   ($st['winrate'] * $items_matches)
      // ) / ($p + $st['purchases']);

      $report['items']['stats'][$hid][$iid]['wins'] += $st['wins'];
      $report['items']['stats'][$hid][$iid]['winrate'] = $report['items']['stats'][$hid][$iid]['wins']/$report['items']['stats'][$hid][$iid]['purchases'];

      $report['items']['stats'][$hid][$iid]['min_time'] = min($report['items']['stats'][$hid][$iid]['min_time'], $st['min_time']);
      $report['items']['stats'][$hid][$iid]['max_time'] = max($report['items']['stats'][$hid][$iid]['max_time'], $st['max_time']);

      $report['items']['stats'][$hid][$iid]['avg_time'] = round( (
        ($report['items']['stats'][$hid][$iid]['avg_time'] * $p) + 
        ($st['avg_time'] * $st['purchases'])
      ) / ($p + $st['purchases']) );

      $report['items']['stats'][$hid][$iid]['q1'] = round( (
        ($report['items']['stats'][$hid][$iid]['q1'] * $p) + 
        ($st['q1'] * $st['purchases'])
      ) / ($p + $st['purchases']) );

      $report['items']['stats'][$hid][$iid]['q3'] = round( (
        ($report['items']['stats'][$hid][$iid]['q3'] * $p) + 
        ($st['q3'] * $st['purchases'])
      ) / ($p + $st['purchases']) );

      $report['items']['stats'][$hid][$iid]['median'] = round( (
        ($report['items']['stats'][$hid][$iid]['median'] * $p) + 
        ($st['median'] * $st['purchases'])
      ) / ($p + $st['purchases']) );

      $report['items']['stats'][$hid][$iid]['early_wr'] = round( (
        ($report['items']['stats'][$hid][$iid]['early_wr'] * $p) + 
        ($st['early_wr'] * $st['purchases'])
      ) / ($p + $st['purchases']), 4 );

      $report['items']['stats'][$hid][$iid]['late_wr'] = round( (
        ($report['items']['stats'][$hid][$iid]['late_wr'] * $p) + 
        ($st['late_wr'] * $st['purchases'])
      ) / ($p + $st['purchases']), 4 );

      if (!(($total_items_matches - $p) + ($items_matches-$st['purchases']))) {
        $report['items']['stats'][$hid][$iid]['wo_wr'] = 0;
      } else {
        $report['items']['stats'][$hid][$iid]['wo_wr'] = round( (
          ($report['items']['stats'][$hid][$iid]['wo_wr'] * ($total_items_matches - $p)) + 
          ($st['wo_wr'] * ($items_matches-$st['purchases']))
        ) / (($total_items_matches - $p) + ($items_matches-$st['purchases'])), 4 );
      }

      $min = (abs($report['items']['stats'][$hid][$iid]['q3']) - abs($report['items']['stats'][$hid][$iid]['q1']))/60;
      $report['items']['stats'][$hid][$iid]['grad'] = round(
        ($report['items']['stats'][$hid][$iid]['late_wr']-$report['items']['stats'][$hid][$iid]['early_wr'])/($min > 1 ? $min : 1),
        4
      );
  
      $report['items']['stats'][$hid][$iid]['prate'] = $report['items']['stats'][$hid][$iid]['purchases']/
        (($total_items_matches + $items_matches) * ($hid == "total" ? 10 : 1));
      }
  }
}

$pcounts = [];
$report['items']['pi'] = [];

//pi
foreach ($source_reports as &$rep) {
  $rep['items']['pi'] = unwrap_data($rep['items']['pi']);

  if (empty($report['items']['pi'])) {
    $report['items']['pi'] = $rep['items']['pi'];
    foreach ($rep['items']['pi'] as $id => $data) {
      $pcounts[$id] = $rep['items']['stats']['total'][$id]['purchases'];
    }
    continue;
  }

  foreach ($rep['items']['pi'] as $id => $data) {
    if (empty($report['items']['pi'][$id])) {
      $report['items']['pi'][$id] = $data;
      $pcounts[$id] = $rep['items']['stats']['total'][$id]['purchases'];
      continue;
    }
    if (empty($data)) continue;

    $p = $rep['items']['stats']['total'][$id]['purchases'];

    $report['items']['pi'][$id]['q1'] = round( (
      ($report['items']['pi'][$id]['q1'] * $pcounts[$id]) + 
      ($data['q1'] * $p)
    ) / ($pcounts[$id] + $p) );

    $report['items']['pi'][$id]['q3'] = round( (
      ($report['items']['pi'][$id]['q3'] * $pcounts[$id]) + 
      ($data['q3'] * $p)
    ) / ($pcounts[$id] + $p) );

    $report['items']['pi'][$id]['med'] = round( (
      ($report['items']['pi'][$id]['med'] * $pcounts[$id]) + 
      ($data['med'] * $p)
    ) / ($pcounts[$id] + $p) );

    $pcounts[$id] += $rep['items']['stats']['total'][$id]['purchases'];
  }
}

$pcounts = [];
$report['items']['ph'] = [];

// ph
foreach ($source_reports as &$rep) {
  $rep['items']['ph'] = unwrap_data($rep['items']['ph']);

  if (empty($report['items']['ph'])) {
    $report['items']['ph'] = $rep['items']['ph'];
    foreach ($rep['items']['ph'] as $id => $data) {
      $pcounts[$id] = $rep['items']['stats'][$id][29]['purchases'];
    }
    continue;
  }

  foreach ($rep['items']['ph'] as $id => $data) {
    if (empty($report['items']['ph'][$id])) {
      $report['items']['ph'][$id] = $data;
      $pcounts[$id] = $rep['items']['stats'][$id][29]['purchases'];
      continue;
    }
    if (empty($data)) continue;

    $p = $rep['items']['stats'][$id][29]['purchases'];

    $report['items']['ph'][$id]['q1'] = round( (
      ($report['items']['ph'][$id]['q1'] * $pcounts[$id]) + 
      ($data['q1'] * $p)
    ) / ($pcounts[$id] + $p) );

    $report['items']['ph'][$id]['q3'] = round( (
      ($report['items']['ph'][$id]['q3'] * $pcounts[$id]) + 
      ($data['q3'] * $p)
    ) / ($pcounts[$id] + $p) );

    $report['items']['ph'][$id]['med'] = round( (
      ($report['items']['ph'][$id]['med'] * $pcounts[$id]) + 
      ($data['med'] * $p)
    ) / ($pcounts[$id] + $p) );

    $pcounts[$id] += $rep['items']['stats'][$id][29]['purchases'];
  }
}

//records
$report['items']['records'] = [];

foreach ($source_reports as &$rep) {
  $rep['items']['records'] = unwrap_data($rep['items']['records']);

  if (empty($report['items']['records'])) {
    $report['items']['records'] = $rep['items']['records'];
    continue;
  }

  foreach ($rep['items']['records'] as $iid => $data) {
    foreach ($data as $hid => $record) {
      if (empty($record)) continue;
      if (empty($report['items']['records'][$iid][$hid])) {
        $report['items']['records'][$iid][$hid] = $record;
      }

      if ($record['time'] < $report['items']['records'][$iid][$hid]['time']) {
        $report['items']['records'][$iid][$hid] = $record;
      }
    }
  }
}

//combos
$report['items']['combos'] = [];

foreach ($source_reports as &$rep) {
  $rep['items']['combos'] = unwrap_data($rep['items']['combos']);

  if (empty($report['items']['combos'])) {
    $report['items']['combos'] = $rep['items']['combos'];
    continue;
  }

  foreach ($rep['items']['combos'] as $iid1 => $pairs) {
    foreach ($pairs as $iid2 => $data) {
      if (empty($data)) continue;
      if ($iid2 == "_h" || !$data['matches']) continue;

      if (empty($report['items']['combos'][$iid1][$iid2])) {
        $report['items']['combos'][$iid1][$iid2] = $data;
        continue;
      }

      $report['items']['combos'][$iid1][$iid2]['time_diff'] = (
        $report['items']['combos'][$iid1][$iid2]['time_diff'] * $report['items']['combos'][$iid1][$iid2]['matches'] + 
        $data['time_diff'] * $data['matches']
      ) / ($report['items']['combos'][$iid1][$iid2]['matches'] + $data['matches']);

      $report['items']['combos'][$iid1][$iid2]['wr_diff'] = (
        $report['items']['combos'][$iid1][$iid2]['wr_diff'] * $report['items']['combos'][$iid1][$iid2]['matches'] + 
        $data['wr_diff'] * $data['matches']
      ) / ($report['items']['combos'][$iid1][$iid2]['matches'] + $data['matches']);

      $report['items']['combos'][$iid1][$iid2]['matches'] += $data['matches'];
      $report['items']['combos'][$iid1][$iid2]['wins'] += $data['wins'];
      $report['items']['combos'][$iid1][$iid2]['exp'] += $data['exp'];
    }
  }
}

// progression (main)

$progpairs = [];

foreach ($source_reports as &$rep) {
  $rep['items']['progr'] = unwrap_data($rep['items']['progr']);

  foreach ($rep['items']['progr'] as $hid => $pairs) {
    if (empty($progpairs[$hid])) $progpairs[$hid] = [];

    foreach ($pairs as $data) {
      if (empty($data)) continue;

      if (empty($progpairs[$hid][ $data['item1'].'-'.$data['item2'] ])) {
        $progpairs[$hid][ $data['item1'].'-'.$data['item2'] ] = $data;
        continue;
      }

      $p = $progpairs[$hid][ $data['item1'].'-'.$data['item2'] ];

      $p['avgord1'] = round( (
        $p['avgord1'] * $p['total'] + $data['avgord1'] * $data['total']
      ) / ($p['total'] + $data['total']), 1 );

      $p['avgord2'] = round( (
        $p['avgord2'] * $p['total'] + $data['avgord2'] * $data['total']
      ) / ($p['total'] + $data['total']), 1 );

      $p['min_diff'] = round( (
        $p['min_diff'] * $p['total'] + $data['min_diff'] * $data['total']
      ) / ($p['total'] + $data['total']), 1 );

      $p['total'] += $data['total'];
      $p['wins'] += $data['wins'];
      $p['winrate'] = round($data['wins']/$data['total'], 4);
    }
  }
}

$report['items']['progr'] = [];
foreach ($progpairs as $hid => $pairs) {
  $report['items']['progr'][$hid] = array_values($pairs);
}

// progression (roles)

$progpairs = [];

foreach ($source_reports as &$rep) {
  foreach ($rep['items']['progrole']['data'] as $hid => $roles) {
    if (empty($progpairs[$hid])) $progpairs[$hid] = [];

    foreach ($roles as $roleid => $pairs) {
      if (empty($progpairs[$hid][$roleid])) $progpairs[$hid][$roleid] = [];

      foreach ($pairs as $data) {
        $data = array_combine($rep['items']['progrole']['keys'], $data);

        if (empty($progpairs[$hid][$roleid][ $data['item1'].'-'.$data['item2'] ])) {
          $progpairs[$hid][$roleid][ $data['item1'].'-'.$data['item2'] ] = $data;
          continue;
        }
  
        $p = $progpairs[$hid][$roleid][ $data['item1'].'-'.$data['item2'] ];
  
        $p['avgord1'] = round( (
          $p['avgord1'] * $p['total'] + $data['avgord1'] * $data['total']
        ) / ($p['total'] + $data['total']), 1 );
  
        $p['avgord2'] = round( (
          $p['avgord2'] * $p['total'] + $data['avgord2'] * $data['total']
        ) / ($p['total'] + $data['total']), 1 );
  
        $p['min_diff'] = round( (
          $p['min_diff'] * $p['total'] + $data['min_diff'] * $data['total']
        ) / ($p['total'] + $data['total']), 1 );
  
        $p['total'] += $data['total'];
        $p['wins'] += $data['wins'];
        $p['winrate'] = round($data['wins']/$data['total'], 4);
      }
    }
  }
}

$report['items']['progrole'] = [
  'keys' => $rep['items']['progrole']['keys'],
  'data' => [],
];
foreach ($progpairs as $hid => $roles) {
  foreach ($roles as $roleid => $pairs) {
    $report['items']['progrole']['data'][$hid][$roleid] = [];
    foreach ($pairs as $pair) {
      $report['items']['progrole']['data'][$hid][$roleid] = array_values($pair);
    }
  }
}

$report['items']['stats'] = wrap_data($report['items']['stats'], true, true, true);
$report['items']['pi'] = wrap_data($report['items']['pi'], true, true, true);
$report['items']['ph'] = wrap_data($report['items']['ph'], true, true, true);
$report['items']['records'] = wrap_data($report['items']['records'], true, true, true);
$report['items']['combos'] = wrap_data($report['items']['combos'], true, true, true);
$report['items']['progr'] = wrap_data($report['items']['progr'], true, true, true);

file_put_contents($input.".old", $src);
file_put_contents($input, json_encode($report));