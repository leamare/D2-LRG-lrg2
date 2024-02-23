<?php 

echo "[S] Requested data for ITEMS STATS - ";

$r = [];

include __DIR__ . "/stats/pi_ph.php";

// Medians and timings

$dataset = [];

$heroline = "";

foreach ($r as $hid => $items) {
  echo ".";

  // $q = "SELECT SUM(NOT a.radiantWin XOR a.isRadiant) win, SUM(1) total
  // FROM (
  //   SELECT matches.radiantWin, matchlines.isRadiant
  //   FROM matchlines
  //   JOIN matches ON matches.matchid = matchlines.matchid
  //   JOIN items ON matches.matchid = items.matchid AND matchlines.heroid = items.hero_id 
  //   ".(is_numeric($hid) ? " WHERE matchlines.heroid = $hid " : "")."
  //   GROUP BY matchlines.matchid, matchlines.heroid
  // ) a";

  $q = "SELECT SUM(NOT m.radiantWin XOR ml.isRadiant) win, SUM(1) total
  FROM matchlines ml join matches m on m.matchid = ml.matchid
  where ml.matchid in (
    select matchid from items
  )".(is_numeric($hid) ? " AND ml.heroid = $hid " : "");

  $query_res = $conn->query($q);

  if ($query_res !== FALSE);
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n".$q);

  if (isset($query_res->num_rows) && $query_res->num_rows) {
    $row = $query_res->fetch_row();
    $hero_wins = $row[0];
    $hero_total = $row[1];
  }
  
  $query_res->close();

  if ($mysql_median) {
    $quants = [];

    if (is_numeric($hid)) {
      $q = "SELECT 
        meds.quant quant,
        meds.item_id item_id,
        meds.hero_id hero_id,
        meds.med_time med_time,
        meds.min_min_time min_min_time,
        SUM(CASE WHEN (min_time < med_med_time AND min_time >= min_min_time AND min_time <= max_min_time) THEN m.radiantWin = ml.isRadiant ELSE 0 END) left_wins,
        SUM(CASE WHEN (min_time < med_med_time AND min_time >= min_min_time AND min_time <= max_min_time) THEN 1 ELSE 0 END) left_match,
        meds.med_med_time med_med_time,
        SUM(CASE WHEN (min_time >= med_med_time AND min_time >= min_min_time AND min_time <= max_min_time) THEN m.radiantWin = ml.isRadiant ELSE 0 END) right_wins,
        SUM(CASE WHEN (min_time >= med_med_time AND min_time >= min_min_time AND min_time <= max_min_time) THEN 1 ELSE 0 END) right_match,
        meds.max_min_time max_min_time
      FROM 
        (
          SELECT *, min(`time`) as min_time from items
          WHERE items.hero_id = $hid
          GROUP BY matchid, hero_id, item_id
        ) items
        JOIN matches m ON items.matchid = m.matchid 
        JOIN matchlines ml ON items.matchid = ml.matchid AND items.hero_id = ml.heroid 
        JOIN 
        (
          SELECT
            (`min_time` > med_time) as quant,
            items.item_id,
            items.hero_id,
            median(min_time) as med_med_time,
            min(min_time) min_min_time,
            max(min_time) max_min_time,
            med_time
          FROM (
            select *, min(`time`) as min_time from items
            WHERE items.hero_id = $hid
            GROUP BY matchid, hero_id, item_id
          ) items JOIN (
            SELECT median(`min_time`) as med_time, hero_id, item_id FROM (
              select *, min(`time`) as min_time from items
              WHERE items.hero_id = $hid
              GROUP BY matchid, hero_id, item_id
            ) q GROUP BY 2, 3
          ) medt ON items.hero_id = medt.hero_id AND items.item_id = medt.item_id
          GROUP BY 1, 2
        ) meds ON items.hero_id = meds.hero_id AND items.item_id = meds.item_id
      GROUP BY 1, 2 ORDER BY 2, 1 ASC;";

      $res = $conn->query($q);
      if ($res);
      else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n".$q);

      for ($row = $res->fetch_assoc(); $row != null; $row = $res->fetch_assoc()) {
        if (!isset($quants[ $row['item_id'] ])) {
          $quants[ $row['item_id'] ] = [];
        }
        $quants[ $row['item_id'] ][ $row['quant'] ] = $row;
      }
    } else {
      foreach ($items as $iid => $data) {
        echo ",";
        $q = "SELECT 
          meds.quant,
          meds.item_id,
          meds.hero_id,
          meds.med_time,
          meds.min_min_time,
          SUM(CASE WHEN (min_time < med_med_time AND min_time >= min_min_time AND min_time <= max_min_time) THEN m.radiantWin = ml.isRadiant ELSE 0 END) left_wins,
          SUM(CASE WHEN (min_time < med_med_time AND min_time >= min_min_time AND min_time <= max_min_time) THEN 1 ELSE 0 END) left_match,
          meds.med_med_time,
          SUM(CASE WHEN (min_time >= med_med_time AND min_time >= min_min_time AND min_time <= max_min_time) THEN m.radiantWin = ml.isRadiant ELSE 0 END) right_wins,
          SUM(CASE WHEN (min_time >= med_med_time AND min_time >= min_min_time AND min_time <= max_min_time) THEN 1 ELSE 0 END) right_match,
          meds.max_min_time
        FROM 
          (
            SELECT *, min(`time`) as min_time from items
            WHERE items.item_id = $iid 
            GROUP BY matchid, hero_id, item_id
          ) items
          JOIN matches m ON items.matchid = m.matchid 
          JOIN matchlines ml ON items.matchid = ml.matchid AND items.hero_id = ml.heroid 
          JOIN 
          (
            SELECT
              (`min_time` > med_time) as quant,
              items.item_id,
              items.hero_id,
              median(min_time) as med_med_time,
              min(min_time) min_min_time,
              max(min_time) max_min_time,
              med_time
            FROM (
              select *, min(`time`) as min_time from items
              WHERE items.item_id = $iid
              GROUP BY matchid, hero_id, item_id
            ) items JOIN (
              SELECT median(`min_time`) as med_time, hero_id, item_id FROM (
                select *, min(`time`) as min_time from items
                WHERE items.item_id = $iid 
                GROUP BY matchid, hero_id, item_id
              ) q GROUP BY 3
            ) medt ON items.item_id = medt.item_id
            GROUP BY 1, 2
          ) meds ON items.item_id = meds.item_id
        GROUP BY 1, 2 ORDER BY 2, 1 ASC;";

        $res = $conn->query($q);
        if ($res);
        else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n".$q);

        for ($row = $res->fetch_assoc(); $row != null; $row = $res->fetch_assoc()) {
          if (!isset($quants[ $row['item_id'] ])) {
            $quants[ $row['item_id'] ] = [];
          }
          $quants[ $row['item_id'] ][ $row['quant'] ] = $row;
        }
      }
    }

    foreach ($items as $iid => $data) {
      if (!isset($dataset[$iid])) $dataset[$iid] = [];
      $dataset[$iid][$hid] = [];

      if ($data['purchases'] < 2) {
        $dataset[$iid][$hid]['sz'] = 0;
        continue;
      }
  
      $dataset[$iid][$hid]['sz'] = $data['purchases'];
    }

    foreach ($quants as $iid => $lines) {
      // if (!empty($lines)) {
        $dataset[$iid][$hid]['q1'] = $lines[0]['med_med_time'];
        $dataset[$iid][$hid]['q1m'] = $lines[0]['left_match'];
        $dataset[$iid][$hid]['q1w'] = $lines[0]['left_wins'];

        $dataset[$iid][$hid]['m'] = $lines[0]['med_time'];
      // }

      if (!isset($lines[1])) {
        $dataset[$iid][$hid]['q3'] = $items[$iid]['max_time'];
        $dataset[$iid][$hid]['q3m'] = 0;
        $dataset[$iid][$hid]['q3w'] = 0;
      } else {
        $dataset[$iid][$hid]['q3'] = $lines[1]['med_med_time'];
        $dataset[$iid][$hid]['q3m'] = $lines[1]['right_match'];
        $dataset[$iid][$hid]['q3w'] = $lines[1]['right_wins'];
      }

      $dataset[$iid][$hid]['wom'] = $hero_total - $items[$iid]['matchcount'];

      if ($dataset[$iid][$hid]['wom']) {
        $dataset[$iid][$hid]['wow'] = $hero_wins - $items[$iid]['wins'];
      } else {
        $dataset[$iid][$hid]['wom'] = 1;
        $dataset[$iid][$hid]['wow'] = 0;
      }
    }
  } else {
    foreach ($items as $iid => $data) {
      if (!isset($dataset[$iid])) $dataset[$iid] = [];
      $dataset[$iid][$hid] = [];
  
      $dataset[$iid][$hid]['q1'] = 0;
      $dataset[$iid][$hid]['m'] = 0;
      $dataset[$iid][$hid]['q3'] = 0;
  
      $dataset[$iid][$hid]['q1w'] = 0;
      $dataset[$iid][$hid]['q3w'] = 0;
      $dataset[$iid][$hid]['q1m'] = 0;
      $dataset[$iid][$hid]['q3m'] = 0;
  
      $dataset[$iid][$hid]['wow'] = 0;
      $dataset[$iid][$hid]['wom'] = 0;
  
      if ($data['purchases'] < 2) {
        $dataset[$iid][$hid]['sz'] = 0;
        continue;
      }
  
      $dataset[$iid][$hid]['sz'] = $data['purchases'];
  
      $qs = [];
  
      $qs['q1'] = round($data['purchases'] * 0.25);
      $qs['q2'] = round($data['purchases'] * 0.5);
      $qs['q3'] = round($data['purchases'] * 0.75);
  
      $q = "SET @rn := 0;
        SET @rw := 0;
        SELECT * from ( 
          SELECT *, 
            @rn := @rn + 1 as rn, 
            @rw := CASE WHEN @rn-1 in (".implode(',', $qs).") THEN 0 ELSE @rw END + a.win as rw
          from (
            SELECT min(items.time) mintime, (NOT matches.radiantWin XOR matchlines.isRadiant) win 
            FROM items 
            JOIN matchlines ON matchlines.matchid = items.matchid AND matchlines.heroid = items.hero_id 
            JOIN matches ON matches.matchid = matchlines.matchid
            WHERE items.item_id = $iid ".(is_numeric($hid) ? "AND items.hero_id = $hid " : "")."
            GROUP BY items.matchid, items.hero_id ORDER BY mintime ASC
          ) a
        ) b 
      WHERE b.rn in (".implode(',', $qs).");";
      
      if ($conn->multi_query($q) === TRUE);
      else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n".$q);
      
      do {
        $query_res = $conn->store_result();
      
        if(!is_bool($query_res)) {
          if ($query_res->num_rows == 0) continue;
  
          $row = $query_res->fetch_row();
  
          if (!empty($row)) {
  
            $dataset[$iid][$hid]['q1'] = $row[0];
            $dataset[$iid][$hid]['q1m'] = $row[2];
            $dataset[$iid][$hid]['q1w'] = $row[3];
  
            $dataset[$iid][$hid]['q3m'] += $row[2];
            $dataset[$iid][$hid]['q3w'] += $row[3];
            $row = $query_res->fetch_row();
            $dataset[$iid][$hid]['m'] = $row[0];
  
            $dataset[$iid][$hid]['q3m'] += $row[2];
            $dataset[$iid][$hid]['q3w'] += $row[3];
            $row = $query_res->fetch_row();
            if ($row) {
              $dataset[$iid][$hid]['q3'] = $row[0];
              $dataset[$iid][$hid]['q3m'] = $data['purchases'] - $row[2];
              $dataset[$iid][$hid]['q3w'] = $data['wins'] - $dataset[$iid][$hid]['q3w'] - $row[3];
            } else {
              $dataset[$iid][$hid]['q3'] = $data['max_time'];
              $dataset[$iid][$hid]['q3m'] = 0;
              $dataset[$iid][$hid]['q3w'] = 0;
            }
  
          }
  
          $query_res->free_result();
        }
      } while($conn->next_result());
      // without
  
      $dataset[$iid][$hid]['wom'] = $hero_total - $data['matchcount'];
  
      if ($dataset[$iid][$hid]['wom']) {
        $dataset[$iid][$hid]['wow'] = $hero_wins - $data['wins'];
      } else {
        $dataset[$iid][$hid]['wom'] = 1;
        $dataset[$iid][$hid]['wow'] = 0;
      }
    }
  }
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