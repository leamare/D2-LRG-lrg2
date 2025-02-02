<?php

// separating timings/wins runs to reduce memory consumption

foreach ($r as $hid => $items) {
  echo ".";

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
}