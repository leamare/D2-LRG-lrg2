<?php
  require_once('locales/en.php');

function join_selectors($modules, $level, $level_codes, $strings, $parent="") {
  $out = "";
  $first = true;
  $selectors = array();
  foreach($modules as $modname => $module) {
    $selectors[] = "<span class=\"mod-".$level_codes[$level][1]."-selector selector".
                        ($first ? " active" : "")."\" onclick=\"switchTab(event, 'module-".(empty($parent) ? "" : $parent."-" ).$modname."', 'mod-".$level_codes[$level][1]."');\">".$strings[$modname]."</span>";
    if(is_array($module)) {
      $module = join_selectors($module, $level+1, $level_codes, $strings, (empty($parent) ? "" : $parent."-" ).$modname);
    }
    $out .= "<div id=\"module-".(empty($parent) ? "" : $parent."-" ).$modname."\" class=\"selector-module mod-".$level_codes[$level][1].($first ? " active" : "")."\">".$module."</div>";
    $first = false;
  }
  return "<div class=\"selector-modules".(empty($level_codes[$level][0]) ? "" : "-".$level_codes[$level][0])."\">".implode($selectors, " | ")."</div>".$out;
}

$level_codes = array(
  # level => array( class-postfix, class-level )
  0 => array ( "", "higher-level" ),
  1 => array ( "sublevel", "lower-level" ),
  2 => array ( "level-3", "level-3" ),
  4 => array ( "level-4", "level-4" )
);

  $leaguetag = "test";

  $output = "";

  $report = file_get_contents("reports/report_".$leaguetag.".json") or die("[F] Can't open $teaguetag, probably no such report\n");
  $report = json_decode($report, true);

  $meta = file_get_contents("res/metadata.json") or die("[F] Can't open metadata\n");
  $meta = json_decode($meta, true);

  $modules = array();
  # module => array or ""
  $modules['overview'] = "";
  if (isset($report['records'])) $modules['records'] = "";
  if (isset($report['averages_heroes']) || isset($report['pickban']) || isset($report['draft']) || isset($report['hero_positions']) ||
      isset($report['hero_sides']) || isset($report['hero_pairs']) || isset($report['hero_triplets']))
        $modules['heroes'] = array();

  if (isset($report['averages_players']) || isset($report['pvp']) || isset($report['player_positions']) || isset($report['player_pairs']))
    $modules['players'] = array();

  if (isset($report['teams'])) $modules['teams'] = array();

  if (isset($report['matches'])) $modules['matches'] = "";

  if (isset($report['players'])) $modules['participants'] = "";


# overview
$h3 = array_rand($report['random']);

$random_caption = "placeholder";
$random_text = "Some random text...";

$modules['overview'] .= "<div class=\"content-text\"><h1>".$random_caption."</h1>".$random_text."</div>";

$modules['overview'] .= "<table class=\"list\" id=\"overview-table\">";
foreach($report['random'] as $key => $value) {
  $modules['overview'] .= "<tr><td>".$strings[$key]."</td><td>".$value."</td></tr>";
}
$modules['overview'] .= "</table>";

# records

if (isset($modules['records'])) {
  $modules['records'] .= "<table id=\"records-module-table\" class=\"list\">
                            <tr class=\"thead\">
                              <th onclick=\"sortTable(0,'records-module-table');\">".$strings['record']."</th>".
                             "<th onclick=\"sortTable(1,'records-module-table');\">".$strings['match']."</th>
                              <th onclick=\"sortTableNum(2,'records-module-table');\">".$strings['value']."</th>
                              <th onclick=\"sortTable(3,'records-module-table');\">".$strings['player']."</th>
                              <th onclick=\"sortTable(4,'records-module-table');\">".$strings['hero']."</th>
                            </tr>";
  foreach($report['records'] as $key => $record) {
    $modules['records'] .= "<tr>
                              <td>".$strings[$key]."</td>
                              <td><a href=\"https://opendota.com/matches/".$record['matchid']."\" alt=\"Match ".$record['matchid']." on OpenDota\" target=\"_blank\">".$record['matchid']."</a></td>
                              <td>".number_format($record['value'],2)."</td>
                              <td>". ($record['playerid'] ?
                                        $report['players'][$record['playerid']] :
                                   "")."</td>
                              <td>".($record['heroid'] ?
                                "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][$record['heroid']]['tag'].
                                ".png\" alt=\"".$meta['heroes'][$record['heroid']]['tag']."\" /> ".
                                $meta['heroes'][$record['heroid']]['name'] : "").
                             "</td>
                          </tr>";
  }

  $modules['records'] .= "</table>";
}

# heroes
if (isset($modules['heroes'])) {
  if (isset($report['averages_heroes'])) {
    $modules['heroes']['averages_heroes'] = "";

    foreach($report['averages_heroes'] as $key => $avg) {
      $modules['heroes']['averages_heroes'] .= "<table id=\"avgs-heroes-".$key."\" class=\"list list-fixed list-small\">
                                                  <caption>".$strings[$key]."</caption>
                                                  <tr class=\"thead\">
                                                    <th>".$strings['hero']."</th>
                                                    <th>".$strings['value']."</th>
                                                  </tr>";
      foreach($avg as $hero) {
        $modules['heroes']['averages_heroes'] .= "<tr>
                                                    <td>".($hero['heroid'] ?
                                                      "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][$hero['heroid']]['tag'].
                                                      ".png\" alt=\"".$meta['heroes'][$hero['heroid']]['tag']."\" /> ".
                                                      $meta['heroes'][$hero['heroid']]['name'] : "").
                                                   "</td><td>".number_format($hero['value'],2)."</td></tr>";
      }
      $modules['heroes']['averages_heroes'] .= "</table>";
    }
  }
  if (isset($report['pickban'])) {
    $modules['heroes']['pickban'] = "";

      $modules['heroes']['pickban'] .=  "<table id=\"heroes-pickban\" class=\"list\">
                                            <tr class=\"thead\">
                                              <th onclick=\"sortTable(0,'heroes-pickban');\">".$strings['hero']."</th>
                                              <th onclick=\"sortTableNum(1,'heroes-pickban');\">".$strings['matches_total']."</th>
                                              <th onclick=\"sortTableNum(2,'heroes-pickban');\">".$strings['matches_picked']."</th>
                                              <th onclick=\"sortTableNum(3,'heroes-pickban');\">".$strings['winrate_picked']."</th>
                                              <th onclick=\"sortTableNum(4,'heroes-pickban');\">".$strings['matches_banned']."</th>
                                              <th onclick=\"sortTableNum(5,'heroes-pickban');\">".$strings['winrate_banned']."</th>
                                            </tr>";
      foreach($report['pickban'] as $hero) {
        $modules['heroes']['pickban'] .=  "<tr>
                                              <td>".($hero['heroid'] ?
                                                "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][$hero['heroid']]['tag'].
                                                ".png\" alt=\"".$meta['heroes'][$hero['heroid']]['tag']."\" /> ".
                                                $meta['heroes'][$hero['heroid']]['name'] : "").
                                             "</td>
                                              <td>".$hero['matches_total']."</td>
                                              <td>".$hero['matches_picked']."</td>
                                              <td>".number_format($hero['winrate_picked']*100,2)."%</td>
                                              <td>".$hero['matches_banned']."</td>
                                              <td>".number_format($hero['winrate_banned']*100,2)."%</td>
                                            </tr>";
      }
      $modules['heroes']['pickban'] .= "</table>";
  }
  if (isset($report['draft'])) {
    $modules['heroes']['draft'] = array();

    for ($i=0; $i<2; $i++) {
      $modules['heroes']['draft'][($i ? "pick" : "ban")."_stages"] = "";
      for ($j=1; $j<4; $j++, isset($report['draft'][$i][$j])) {
        $modules['heroes']['draft'][($i ? "pick" : "ban")."_stages"] .= "<table id=\"heroes-draft-$i-$j\" class=\"list list-small\">
                                          <caption> Stage $j of ".($i ? $strings['picks'] : $strings['bans'])."</caption>
                                          <tr class=\"thead\">
                                            <th onclick=\"sortTable(0,'heroes-draft-$i-$j');\">".$strings['hero']."</th>
                                            <th onclick=\"sortTableNum(1,'heroes-draft-$i-$j');\">".$strings['matches']."</th>
                                            <th onclick=\"sortTableNum(2,'heroes-draft-$i-$j');\">".$strings['winrate']."</th>
                                          </tr>";

        foreach($report['draft'][$i][$j] as $hero) {
          $modules['heroes']['draft'][($i ? "pick" : "ban")."_stages"] .= "<tr>
                                              <td>".($hero['heroid'] ?
                                                "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][$hero['heroid']]['tag'].
                                                ".png\" alt=\"".$meta['heroes'][$hero['heroid']]['tag']."\" /> ".
                                                $meta['heroes'][$hero['heroid']]['name'] : "").
                                             "</td>
                                              <td>".$hero['matches']."</td>
                                              <td>".number_format($hero['winrate']*100,2)."%</td>
                                            </tr>";
        }
        $modules['heroes']['draft'][($i ? "pick" : "ban")."_stages"] .= "</table>";

      }
    }
  }
  if (isset($report['hero_positions'])) {
    $modules['heroes']['hero_positions'] = array();

    for ($i=0; $i<2 && !isset($keys); $i++) {
      for ($j=1; $j<6 && $j>0; $j++) {
        if (!$i) { $j = 0; }
        if(isset($report['hero_positions'][$i][$j][0])) {
          $keys = array_keys($report['hero_positions'][$i][$j][0]);
          break;
        }
        if (!$i) { break; }
      }
    }

    for ($i=0; $i<2; $i++) {
      for ($j=1; $j<6 && $j>0; $j++) {
        if (!$i) { $j = 0; }
        $strings["positions-$i-$j"] = ($i ? $strings['core'] : $strings['support'])." ".$meta['lanes'][$j];

        if(sizeof($report['hero_positions'][$i][$j])) {
          $modules['heroes']['hero_positions']["positions-$i-$j"]  = "";
          $modules['heroes']['hero_positions']["positions-$i-$j"] .= "<table id=\"heroes-positions-$i-$j\" class=\"list wide\">
                                            <tr class=\"thead\">
                                              <th onclick=\"sortTable(0,'heroes-positions-$i-$j');\">".$strings['hero']."</th>";
          for($k=1, $end=sizeof($keys); $k < $end; $k++) {
            $modules['heroes']['hero_positions']["positions-$i-$j"] .= "<th onclick=\"sortTableNum($k,'heroes-positions-$i-$j');\">".$strings[$keys[$k]]."</th>";
          }
          $modules['heroes']['hero_positions']["positions-$i-$j"] .= "</tr>";


          foreach($report['hero_positions'][$i][$j] as $hero) {

            $modules['heroes']['hero_positions']["positions-$i-$j"] .= "<tr".(isset($report['hero_positions_matches']) ?
                                                                      " onclick=\"showModal('".implode($report['hero_positions_matches'][$i][$j][$hero['heroid']], ", ").
                                                                      "', '".$strings['matches']."');\"" : "").">
                                                <td>".($hero['heroid'] ?
                                                  "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $hero['heroid'] ]['tag'].
                                                  ".png\" alt=\"".$meta['heroes'][ $hero['heroid'] ]['tag']."\" /> ".
                                                  $meta['heroes'][ $hero['heroid'] ]['name'] : "").
                                               "</td>
                                                <td>".$hero['matches_s']."</td>
                                                <td>".number_format($hero['winrate_s']*100,1)."%</td>";
            for($k=3, $end=sizeof($keys); $k < $end; $k++) {
              $modules['heroes']['hero_positions']["positions-$i-$j"] .= "<td>".number_format($hero[$keys[$k]],1)."</td>";
            }
            $modules['heroes']['hero_positions']["positions-$i-$j"] .= "</tr>";
          }
          $modules['heroes']['hero_positions']["positions-$i-$j"] .= "</table>";
        }
        if (!$i) { break; }
      }
    }
    unset($keys);
  }
  if (isset($report['hero_sides'])) {
    $modules['heroes']['hero_sides'] = array();

    for ($i=0; $i<2 && !isset($keys); $i++) {
        if(isset($report['hero_sides'][$i][0])) {
          $keys = array_keys($report['hero_sides'][$i][0]);
          break;
        }
    }

    for ($side = 0; $side < 2; $side++) {
      $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] = "";
      $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "<table id=\"hero-sides-".$side."\" class=\"list\">
                                    <tr class=\"thead\">
                                      <th onclick=\"sortTable(0,'hero-sides-$side');\">".$strings['hero']."</th>";
      for($k=1, $end=sizeof($keys); $k < $end; $k++) {
        $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "<th onclick=\"sortTableNum($k,'hero-sides-$side');\">".$strings[$keys[$k]]."</th>";
      }
      $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "</tr>";

      foreach($report['hero_sides'][$side] as $hero) {
        $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "<tr>
                                            <td>".($hero['heroid'] ?
                                              "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $hero['heroid'] ]['tag'].
                                              ".png\" alt=\"".$meta['heroes'][ $hero['heroid'] ]['tag']."\" /> ".
                                              $meta['heroes'][ $hero['heroid'] ]['name'] : "").
                                           "</td>".
                                           "<td>".$hero['matches']."</td>".
                                           "<td>".number_format($hero['winrate']*100,2)."%</td>";
        for($k=3, $end=sizeof($keys); $k < $end; $k++) {
          $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "<td>".number_format($hero[$keys[$k]],2)."</td>";
        }
        $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "</tr>";
      }
      $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "</table>";
    }
    unset($keys);
  }
  if (isset($report['hero_pairs'])) {
    $modules['heroes']['hero_pairs'] = "";

    $modules['heroes']['hero_pairs'] .= "<table id=\"hero-pairs\" class=\"list\">
                                          <tr class=\"thead\">
                                            <th onclick=\"sortTable(0,'hero-pairs');\">".$strings['hero']." 1</th>
                                            <th onclick=\"sortTable(1,'hero-pairs');\">".$strings['hero']." 2</th>
                                            <th onclick=\"sortTableNum(2,'hero-pairs');\">".$strings['matches']."</th>
                                            <th onclick=\"sortTableNum(3,'hero-pairs');\">".$strings['winrate']."</th>
                                          </tr>";
    foreach($report['hero_pairs'] as $pair) {
      $modules['heroes']['hero_pairs'] .= "<tr".(isset($report['hero_pairs_matches']) ?
                                          " onclick=\"showModal('".implode($report['hero_pairs_matches'][$pair['heroid1'].'-'.$pair['heroid2']], ", ").
                                                                "', '".$strings['matches']."');\"" : "").">
                                            <td>".($pair['heroid1'] ?
                                              "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $pair['heroid1'] ]['tag'].
                                              ".png\" alt=\"".$meta['heroes'][ $pair['heroid1'] ]['tag']."\" /> ".
                                              $meta['heroes'][ $pair['heroid1'] ]['name'] : "").
                                           "</td><td>".($pair['heroid2'] ?
                                             "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $pair['heroid2'] ]['tag'].
                                             ".png\" alt=\"".$meta['heroes'][ $pair['heroid2'] ]['tag']."\" /> ".
                                             $meta['heroes'][ $pair['heroid2'] ]['name'] : "").
                                           "</td>
                                           <td>".$pair['matches']."</td>
                                           <td>".$pair['winrate']."</td>
                                          </tr>";
    }
    $modules['heroes']['hero_pairs'] .= "</table>";
  }
  if (isset($report['hero_triplets'])) {
    $modules['heroes']['hero_triplets'] = "";

    $modules['heroes']['hero_triplets'] .= "<table id=\"hero-triplets\" class=\"list\">
                                          <tr class=\"thead\">
                                            <th onclick=\"sortTable(0,'hero-triplets');\">".$strings['hero']." 1</th>
                                            <th onclick=\"sortTable(1,'hero-triplets');\">".$strings['hero']." 2</th>
                                            <th onclick=\"sortTable(2,'hero-triplets');\">".$strings['hero']." 3</th>
                                            <th onclick=\"sortTableNum(3,'hero-triplets');\">".$strings['matches']."</th>
                                            <th onclick=\"sortTableNum(4,'hero-triplets');\">".$strings['winrate']."</th>
                                          </tr>";
    foreach($report['hero_triplets'] as $pair) {
      $modules['heroes']['hero_triplets'] .= "<tr".(isset($report['hero_pairs_matches']) ?
                                          " onclick=\"showModal('".
                                          implode($report['hero_pairs_matches'][$pair['heroid1'].'-'.$pair['heroid2'].'-'.$pair['heroid3']], ", ").
                                                                "', '".$strings['matches']."');\"" : "").">
                                            <td>".($pair['heroid1'] ?
                                              "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $pair['heroid1'] ]['tag'].
                                              ".png\" alt=\"".$meta['heroes'][ $pair['heroid1'] ]['tag']."\" /> ".
                                              $meta['heroes'][ $pair['heroid1'] ]['name'] : "").
                                           "</td><td>".($pair['heroid2'] ?
                                             "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $pair['heroid2'] ]['tag'].
                                             ".png\" alt=\"".$meta['heroes'][ $pair['heroid2'] ]['tag']."\" /> ".
                                             $meta['heroes'][ $pair['heroid2'] ]['name'] : "").
                                           "</td><td>".($pair['heroid3'] ?
                                             "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $pair['heroid3'] ]['tag'].
                                             ".png\" alt=\"".$meta['heroes'][ $pair['heroid3'] ]['tag']."\" /> ".
                                             $meta['heroes'][ $pair['heroid3'] ]['name'] : "").
                                           "</td>
                                           <td>".$pair['matches']."</td>
                                           <td>".$pair['winrate']."</td>
                                          </tr>";
    }
    $modules['heroes']['hero_triplets'] .= "</table>";
  }
}

# players
if (isset($modules['players'])) {
  if (isset($report['averages_players'])) {
    $modules['players']['averages_players'] = "";

    foreach($report['averages_players'] as $key => $avg) {
      $modules['players']['averages_players'] .= "<table id=\"avgs-players-".$key."\" class=\"list list-fixed list-small\">
                                                  <caption>".$strings[$key]."</caption>
                                                  <tr class=\"thead\">
                                                    <th>".$strings['player']."</th>
                                                    <th>".$strings['value']."</th>
                                                  </tr>";
      foreach($avg as $player) {
        $modules['players']['averages_players'] .= "<tr>
                                                    <td>".$report['players'][$player['playerid']].
                                                   "</td><td>".number_format($player['value'],2)."</td></tr>";
      }
      $modules['players']['averages_players'] .= "</table>";
    }
  }
  if (isset($report['pvp'])) {
    $modules['players']['pvp']['grid'] = "";
    $player_ids = array_keys($report['players']);
    $pvp = array();

    $modules['players']['pvp']['grid'] .= "<table  class=\"pvp wide\">";

    $modules['players']['pvp']['grid'] .= "<tr class=\"thead\"><th></th>";
    foreach($report['players'] as $pid => $pname) {
      $modules['players']['pvp']['grid'] .= "<th><span>".$pname."</span></th>";
      $pvp[$pid] = array();
    }
    $modules['players']['pvp']['grid'] .= "</tr>";

    foreach($pvp as $player_id => $playerline) {
      foreach($player_ids as $pid) {
        $pvp[$player_id][$pid] = array(
          "winrate" => 0,
          "matches" => 0,
          "won" => 0,
          "lost" => 0
        );
      }
    }

    foreach($player_ids as $pid) {
      for($i=0, $end = sizeof($report['pvp']); $i<$end; $i++) {
        if($report['pvp'][$i]['playerid1'] == $pid) {
          $pvp[$pid][$report['pvp'][$i]['playerid2']] = array(
            "winrate" => $report['pvp'][$i]['p1winrate'],
            "matches" => $report['pvp'][$i]['matches'],
            "won" => $report['pvp'][$i]['p1won'],
            "lost" => $report['pvp'][$i]['matches'] - $report['pvp'][$i]['p1won']
          );
        }
        if($report['pvp'][$i]['playerid2'] == $pid) {
          $pvp[$pid][$report['pvp'][$i]['playerid1']] = array(
            "winrate" => 1-$report['pvp'][$i]['p1winrate'],
            "matches" => $report['pvp'][$i]['matches'],
            "won" => $report['pvp'][$i]['matches'] - $report['pvp'][$i]['p1won'],
            "lost" => $report['pvp'][$i]['p1won']
          );
        }
      }
    }


    foreach($pvp as $pid => $playerline) {
      $modules['players']['pvp']['grid'] .= "<tr><td>".$report['players'][$pid]."</td>";
      for($i=0, $end = sizeof($player_ids); $i<$end; $i++) {
        if($pid == $player_ids[$i]) {
          $modules['players']['pvp']['grid'] .= "<td class=\"transparent\"></td>";
        } else if($playerline[$player_ids[$i]]['matches'] == 0) {
          $modules['players']['pvp']['grid'] .= "<td>-</td>";
        } else {
          $modules['players']['pvp']['grid'] .= "<td".
                  ($playerline[$player_ids[$i]]['winrate'] > 0.55 ? " class=\"high-wr\"" : (
                        $playerline[$player_ids[$i]]['winrate'] < 0.45 ? " class=\"low-wr\"" : ""
                      )
                    )." onclick=\"showModal('".$strings['matches'].": ".$pvp[$pid][$player_ids[$i]]['matches']
                          ."<br />".$strings['winrate'].": ".number_format($pvp[$pid][$player_ids[$i]]['winrate']*100,2)
                          ."%<br />".$strings['won']." ".$pvp[$pid][$player_ids[$i]]['won']." - "
                                   .$strings['lost']." ".$pvp[$pid][$player_ids[$i]]['lost']."','".$report['players'][$pid]." vs ".$report['players'][$player_ids[$i]]."')\">".
                      number_format($playerline[$player_ids[$i]]['winrate']*100,0)."</td>";
        }
      }
      $modules['players']['pvp']['grid'] .= "</tr>";
    }

    $modules['players']['pvp']['grid'] .= "</table>";

    foreach($pvp as $pid => $playerline) {
      $strings['pid'.$pid] = $report['players'][$pid];
      $modules['players']['pvp']['pid'.$pid] = "<table id=\"player-pvp-$pid\" class=\"list\">";

      $modules['players']['pvp']['pid'.$pid] .= "<tr class=\"thead\">
                                                    <th onclick=\"sortTable(0,'player-pvp-$pid');\">".$strings['opponent']."</th>
                                                    <th onclick=\"sortTableNum(1,'player-pvp-$pid');\">".$strings['winrate']."</th>
                                                    <th onclick=\"sortTableNum(2,'player-pvp-$pid');\">".$strings['matches']."</th>
                                                    <th onclick=\"sortTableNum(3,'player-pvp-$pid');\">".$strings['won']."</th>
                                                    <th onclick=\"sortTableNum(4,'player-pvp-$pid');\">".$strings['lost']."</th>
                                                 </tr>";
      for($i=0, $end = sizeof($player_ids); $i<$end; $i++) {
        if($player_ids[$i] == $pid || $pvp[$pid][$player_ids[$i]]['matches'] == 0) {
          continue;
        } else {
          $modules['players']['pvp']['pid'.$pid] .= "<tr>
                                                        <td>".$report['players'][$player_ids[$i]]."</th>
                                                        <td>".number_format($pvp[$pid][$player_ids[$i]]['winrate']*100,2)."</th>
                                                        <td>".$pvp[$pid][$player_ids[$i]]['matches']."</th>
                                                        <td>".$pvp[$pid][$player_ids[$i]]['won']."</th>
                                                        <td>".$pvp[$pid][$player_ids[$i]]['lost']."</th>
                                                     </tr>";
        }
      }
      $modules['players']['pvp']['pid'.$pid] .= "</table>";
    }
    unset($pvp);
  }
  if (isset($report['player_pairs'])) {
    $modules['players']['player_pairs'] = "";

    $modules['players']['player_pairs'] .= "<table id=\"player-pairs\" class=\"list\">
                                          <tr class=\"thead\">
                                            <th onclick=\"sortTable(0,'player-pairs');\">".$strings['player']." 1</th>
                                            <th onclick=\"sortTable(1,'player-pairs');\">".$strings['player']." 2</th>
                                            <th onclick=\"sortTableNum(2,'player-pairs');\">".$strings['matches']."</th>
                                            <th onclick=\"sortTableNum(3,'player-pairs');\">".$strings['winrate']."</th>
                                          </tr>";
    foreach($report['player_pairs'] as $pair) {
      $modules['players']['player_pairs'] .= "<tr>
                                            <td>".$report['players'][ $pair['playerid1'] ]."</td>
                                            <td>".$report['players'][ $pair['playerid2'] ]."</td>
                                           <td>".$pair['matches']."</td>
                                           <td>".$pair['winrate']."</td>
                                          </tr>";
    }
    $modules['players']['player_pairs'] .= "</table>";
  }
  if (isset($report['player_triplets'])) {
    $modules['players']['player_triplets'] = "";

    $modules['players']['player_triplets'] .= "<table id=\"player-triplets\" class=\"list\">
                                          <tr class=\"thead\">
                                            <th onclick=\"sortTable(0,'player-triplets');\">".$strings['player']." 1</th>
                                            <th onclick=\"sortTable(1,'player-triplets');\">".$strings['player']." 2</th>
                                            <th onclick=\"sortTable(2,'player-triplets');\">".$strings['player']." 3</th>
                                            <th onclick=\"sortTableNum(3,'player-triplets');\">".$strings['matches']."</th>
                                            <th onclick=\"sortTableNum(4,'player-triplets');\">".$strings['winrate']."</th>
                                          </tr>";
    foreach($report['player_triplets'] as $pair) {
      $modules['players']['player_triplets'] .= "<tr>
                                            <td>".$report['players'][ $pair['playerid1'] ]."</td>
                                            <td>".$report['players'][ $pair['playerid2'] ]."</td>
                                            <td>".$report['players'][ $pair['playerid3'] ]."</td>
                                           <td>".$pair['matches']."</td>
                                           <td>".$pair['winrate']."</td>
                                          </tr>";
    }
    $modules['players']['player_triplets'] .= "</table>";
  }
  if (isset($report['player_positions'])) {
    $modules['players']['player_positions'] = array();

    for ($i=0; $i<2 && !isset($keys); $i++) {
      for ($j=1; $j<6 && $j>0; $j++) {
        if (!$i) { $j = 0; }
        if(isset($report['player_positions'][$i][$j][0])) {
          $keys = array_keys($report['player_positions'][$i][$j][0]);
          break;
        }
        if (!$i) { break; }
      }
    }

    for ($i=0; $i<2; $i++) {
      for ($j=1; $j<6 && $j>0; $j++) {
        if (!$i) { $j = 0; }
        $strings["positions-$i-$j"] = ($i ? $strings['core'] : $strings['support'])." ".$meta['lanes'][$j];

        if(sizeof($report['player_positions'][$i][$j])) {
          $modules['players']['player_positions']["positions-$i-$j"]  = "";
          $modules['players']['player_positions']["positions-$i-$j"] .= "<table id=\"players-positions-$i-$j\" class=\"list wide\">
                                            <tr class=\"thead\">
                                              <th onclick=\"sortTable(0,'players-positions-$i-$j');\">".$strings['player']."</th>";
          for($k=1, $end=sizeof($keys); $k < $end; $k++) {
            $modules['players']['player_positions']["positions-$i-$j"] .= "<th onclick=\"sortTableNum($k,'players-positions-$i-$j');\">".$strings[$keys[$k]]."</th>";
          }
          $modules['players']['player_positions']["positions-$i-$j"] .= "</tr>";


          foreach($report['player_positions'][$i][$j] as $player) {

            $modules['players']['player_positions']["positions-$i-$j"] .= "<tr".(isset($report['player_positions_matches']) ?
                                                                      " onclick=\"showModal('".implode($report['player_positions_matches'][$i][$j][$player['playerid']], ", ").
                                                                      "', '".$strings['matches']."');\"" : "").">
                                                <td>".$report['players'][$player['playerid']]."</td>
                                                <td>".$player['matches_s']."</td>
                                                <td>".number_format($player['winrate_s']*100,1)."%</td>";
            for($k=3, $end=sizeof($keys); $k < $end; $k++) {
              $modules['players']['player_positions']["positions-$i-$j"] .= "<td>".number_format($player[$keys[$k]],1)."</td>";
            }
            $modules['players']['player_positions']["positions-$i-$j"] .= "</tr>";
          }
          $modules['players']['player_positions']["positions-$i-$j"] .= "</table>";
        }
        if (!$i) { break; }
      }
    }
    unset($keys);
  }
}
# teams

# matches


  ?>
  <!DOCTYPE html>
  <html>
    <head>
      <link rel="shortcut icon" href="/favicon.ico" />
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
      <title>Dota 2 Report</title>
      <link href="res/valve_mimic.css" rel="stylesheet" type="text/css" />
      <link href="res/reports.css" rel="stylesheet" type="text/css" />
    </head>
    <body>
      <header class="navBar">
        <span class="navItem dotalogo"><a href="#"></a></span>
        <span class="navItem">123</span>
        <span class="navItem">123</span>
        <span class="navItem">123</span>
        <span class="navItem">123</span>
      </header>
      <div id="content-wrapper">
        <div id="header-image" class="section-header">
          <h1><?php echo $report['leaguetag']; ?></h1>
          <h2><?php echo $report['leaguedesc']; ?></h2>
          <h3><?php echo $strings[$h3].": ".$report['random'][$h3]; ?></h3>
        </div>
        <div id="main-section" class="content-section">
          <div id="content-top">
            <div class="content-header"><?php echo $random_caption; ?></div>
            <div class="content-text"><?php echo $random_text; ?></div>
          </div>
<?php

$output = join_selectors($modules, 0, $level_codes, $strings);

echo $output;

?>
          </div>
        </div>
        <footer>

        </footer>
        <div class="modal" id="modal-box">
          <div class="modal-content">
            <div class="modal-header"></div>
            <div id="modal-text" class="modal-text"></div>
          </div>
        </div>
        <script type="text/javascript" src="res/reports.js"></script>
      </body>
    </html>
