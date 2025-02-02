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