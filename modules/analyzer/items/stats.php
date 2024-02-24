<?php 

echo "[S] Requested data for ITEMS STATS - ";

$r = [];

include __DIR__ . "/stats/pi_ph.php";

// Medians and timings

$dataset = [];

if ($schema['medians_available']) {
  include __DIR__ . "/stats/query_medians.php";
} else {
  include __DIR__ . "/stats/query_legacy.php";
}

foreach ($r as $hid => $items) {
  foreach ($items as $iid => $data) {
    $sz = $dataset[$iid][$hid]['sz'];

    if ($sz < 2) {
      $r[$hid][$iid]['q1'] = $data['avg_time'];
      $r[$hid][$iid]['q3'] = $data['avg_time'];
      $r[$hid][$iid]['median'] = $data['avg_time'];
      $r[$hid][$iid]['winrate'] = $data['wins'];
      
      $r[$hid][$iid]['prate'] = round($data['purchases']/$items_matches[$hid], 4);
      $r[$hid][$iid]['std_dev'] = 0;

      $r[$hid][$iid]['early_wr'] = $r[$hid][$iid]['winrate'];
      $r[$hid][$iid]['late_wr'] = $r[$hid][$iid]['winrate'];
      $r[$hid][$iid]['wo_wr'] = $r[$hid][$iid]['winrate'];
      $r[$hid][$iid]['grad'] = 0;

      continue;
    }

    $r[$hid][$iid]['q1'] = $dataset[$iid][$hid]['q1'];
    $r[$hid][$iid]['q3'] = $dataset[$iid][$hid]['q3'] ?? $dataset[$iid][$hid]['m'];
    $r[$hid][$iid]['median'] = $dataset[$iid][$hid]['m'];
    $r[$hid][$iid]['winrate'] = round($data['wins']/$data['purchases'], 4);
    $r[$hid][$iid]['prate'] = round($data['purchases']/$items_matches[$hid], 4);


    // using estimation formula for Standard Deviation because
    // I don't want to make another query
    // $r[$hid][$iid]['std_dev'] = ($r[$hid][$iid]['max_time'] - $r[$hid][$iid]['min_time']) / 4*inverse_ncdf(($sz - 0.375)/($sz + 0.25))
    //   + ($r[$hid][$iid]['q3'] - $r[$hid][$iid]['q1']) / 4*inverse_ncdf((0.75*$sz - 0.125)/($sz + 0.25));

    // it's an approximation, but a decent one, really
    $r[$hid][$iid]['std_dev'] = round( ($r[$hid][$iid]['q3'] - $r[$hid][$iid]['q1']) / 1.35 , 3);

    $q1 = $r[$hid][$iid]['q1'];
    $q3 = $r[$hid][$iid]['q3'];

    $total_q1 = $dataset[$iid][$hid]['q1m'];
    $wins_q1 = $dataset[$iid][$hid]['q1w'];
    $total_q3 = $dataset[$iid][$hid]['q3m'];
    $wins_q3 = $dataset[$iid][$hid]['q3w'];

    $total_wo = $dataset[$iid][$hid]['wom'];
    $wins_wo = $dataset[$iid][$hid]['wow'];

    $r[$hid][$iid]['early_wr'] = $total_q1 ? round($wins_q1/$total_q1, 4) : $r[$hid][$iid]['winrate'];
    $r[$hid][$iid]['late_wr'] = $total_q3 ? round($wins_q3/$total_q3, 4) : $r[$hid][$iid]['winrate'];
    $r[$hid][$iid]['wo_wr'] = $total_wo ? round($wins_wo/$total_wo, 4) : 0;

    if ($sz > $purchases_h[$hid]['med'] && ($q3-$q1)) {
      $min = (abs($q3)-abs($q1))/60;
      $r[$hid][$iid]['grad'] = round( ($r[$hid][$iid]['late_wr']-$r[$hid][$iid]['early_wr'])/($min > 1 ? $min : 1) , 4 );
    } else 
    $r[$hid][$iid]['grad'] = 0;
  }
}

unset($dataset);

// std_dev
// q1 q3 median
// winrate
// purchase rate

echo "\n";

$result['items']['stats'] = $r;
$result['items']['pi'] = $purchases_i;
$result['items']['ph'] = $purchases_h;